<?php
// Job management API endpoint - Handles job submission (with AES encryption) and retrieval (with role-based filtering)

header('Content-Type: application/json');
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/crypt.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/hmac.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/validation.php';

// Initialize
$db = getDB();
$auth = new Auth($db);
$logger = new ActivityLogger();

// Get headers
$headers = getallheaders();
$session_id = $headers['X-Session-ID'] ?? '';
$token = $headers['X-Token'] ?? '';

// Verify session
$user = $auth->verifySession($session_id, $token);
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userid = $user['userid'];
$role = $user['role'] ?? RBAC::ROLE_GUEST;

// POST: Create new job
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!csrf_validate_request()) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF token validation failed']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // RBAC Check
    if (!RBAC::canSubmitJobs($role)) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied: Cannot submit jobs']);
        exit;
    }
    
    if (!isset($input['job_name']) || !isset($input['opn_number'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }

    // Validate input
    $job_name_validation = Validator::validateJobName($input['job_name']);
    $opn_validation = Validator::validateOpnNumber($input['opn_number']);
    
    if (!$job_name_validation['valid']) {
        http_response_code(400);
        echo json_encode(['error' => $job_name_validation['error']]);
        exit;
    }
    
    if (!$opn_validation['valid']) {
        http_response_code(400);
        echo json_encode(['error' => $opn_validation['error']]);
        exit;
    }

    $job_name = trim($input['job_name']);
    $opn_number = $opn_validation['value'];
    $clear_text_data = isset($input['clear_text_data']) ? trim($input['clear_text_data']) : '';
    
    // Validate clear_text_data length
    if (strlen($clear_text_data) > 1000) {
        http_response_code(400);
        echo json_encode(['error' => 'Description is too long (max 1000 characters)']);
        exit;
    }

    // Generate HMAC for integrity
    $data_hmac = HMAC::generateForJob($job_name, $opn_number);
    
    // Encrypt sensitive data
    $encrypted_opn = AES::_encrypt($opn_number);

    try {
        // Store encrypted OPN only (plaintext removed for security)
        $stmt = $db->prepare("INSERT INTO jobs (userid, job_name, opn_number_encrypted, clear_text_data, data_hmac) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userid, $job_name, $encrypted_opn, $clear_text_data, $data_hmac]);
        $jobid = $db->lastInsertId();
        
        // Log activity
        $logger->logJobSubmission($userid, $jobid, $job_name);
        $logger->logDataTransfer($userid, 'JOB_DATA', "Job ID {$jobid} submitted with HMAC verification");

        http_response_code(201);
        echo json_encode([
            'success' => true, 
            'jobid' => $jobid,
            'hmac' => $data_hmac // Return HMAC for verification
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
} 
// GET: Retrieve jobs
elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // RBAC Check
    if ($role === RBAC::ROLE_GUEST) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied: Guest cannot view jobs']);
        exit;
    }

    $logger->logJobView($userid, $role);

    // Query construction based on role
    $query = "SELECT jobid, job_name, opn_number_encrypted, clear_text_data, data_hmac, created_at, userid FROM jobs";
    $params = [];

    // If not admin, restrict to own jobs
    if (!RBAC::canViewSensitiveData($role)) {
        $query .= " WHERE userid = ?";
        $params[] = $userid;
    }
    
    $query .= " ORDER BY created_at DESC";

    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        //The HMAC Check and which job is failing and the name of the job
        // Process data based on permissions
        foreach ($jobs as &$job) {
            // Verify HMAC integrity
            $decrypted_opn = AES::_decrypt($job['opn_number_encrypted']);
            $isValid = HMAC::verifyJob($job['job_name'], $decrypted_opn, $job['data_hmac']);
            
            // Add verification status for all roles that can view encrypted data
            if (RBAC::canViewEncrypted($role)) {
                $job['hmac_verified'] = $isValid;
                $job['hmac_status'] = $isValid ? 'verified' : 'failed';
                
                if (!$isValid) {
                    $job['integrity_check'] = 'FAILED'; // Alert admin
                    $logger->logHMACVerification($userid, false, "Integrity check failed for Job ID {$job['jobid']}");
                }
            }

            // Handle sensitive data visibility
            if (RBAC::canViewSensitiveData($role)) {
                // Admin sees everything
                $job['opn_number'] = $decrypted_opn;
            } elseif (RBAC::canViewPlaintext($role)) {
                // User sees plaintext, but sensitive OPN is hidden
                $job['opn_number'] = '[Sensitive Data Hidden]';
                unset($job['opn_number_encrypted']); // Remove encrypted version
                unset($job['data_hmac']); // Remove HMAC
            } else {
                // Should be caught by initial check, but safe fallback
                $job['opn_number'] = '[Access Denied]';
                $job['clear_text_data'] = '[Access Denied]';
                unset($job['opn_number_encrypted']);
                unset($job['data_hmac']);
            }
        }

        echo json_encode(['success' => true, 'jobs' => $jobs]);
    } catch (PDOException $e) {
        error_log("Job retrieval database error: " . $e->getMessage());
        http_response_code(500);
        $error_msg = (defined('APP_ENV') && APP_ENV === 'production') 
            ? 'Database error occurred' 
            : 'Database error';
        echo json_encode(['error' => $error_msg]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
