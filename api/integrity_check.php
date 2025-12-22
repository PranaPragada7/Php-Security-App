<?php
// Integrity check API endpoint (Admin only) - Verifies HMAC integrity for all jobs

header('Content-Type: application/json');
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/hmac.php';
require_once __DIR__ . '/../includes/crypt.php';

// Polyfill for getallheaders
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

try {
    require_once __DIR__ . '/../config/database.php';
    $db = getDB();
    $auth = new Auth($db);
    
    // Auth Check
    $headers = getallheaders();
    $session_id = $headers['X-Session-ID'] ?? $headers['X-Session-Id'] ?? '';
    $token = $headers['X-Token'] ?? '';
    
    $session = $auth->verifySession($session_id, $token);
    if (!$session || !RBAC::canViewSensitiveData($session['role'] ?? 'guest')) { // Only admins/privileged users
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    // Fetch all jobs to verify HMAC
    // We only need the columns required for HMAC regeneration
    $stmt = $db->prepare("SELECT jobid, job_name, opn_number_encrypted, data_hmac FROM jobs");
    $stmt->execute();
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $compromised_jobs = [];

    foreach ($jobs as $job) {
        // 1. Decrypt the sensitive data to get the original value
        try {
            $decrypted_opn = AES::_decrypt($job['opn_number_encrypted']);
            
            if ($decrypted_opn === false) {
                // If decryption fails, the data is definitely corrupted
                 $compromised_jobs[] = [
                    'jobid' => $job['jobid'],
                    'job_name' => $job['job_name'],
                    'reason' => 'Decryption Failed'
                ];
                continue;
            }

            // 2. Re-calculate HMAC using the retrieved data
            // Note: In api/jobs.php, we generated HMAC using: HMAC::generateForJob($job_name, $opn_number)
            // 3. Compare with stored HMAC
            if (!HMAC::verifyJob($job['job_name'], $decrypted_opn, $job['data_hmac'])) {
                $compromised_jobs[] = [
                    'jobid' => $job['jobid'],
                    'job_name' => $job['job_name'],
                    'reason' => 'HMAC Mismatch'
                ];
            }

        } catch (Exception $e) {
            $compromised_jobs[] = [
                'jobid' => $job['jobid'],
                'job_name' => $job['job_name'],
                'reason' => 'Verification Error'
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'compromised_count' => count($compromised_jobs),
        'compromised_jobs' => $compromised_jobs
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

