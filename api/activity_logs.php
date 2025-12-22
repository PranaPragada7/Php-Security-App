<?php
// Activity logs API endpoint (Admin only) - Retrieves filtered audit logs

header('Content-Type: application/json');
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/logger.php';

$db = getDB();
$auth = new Auth($db);
$logger = new ActivityLogger();

// Verify session
$headers = getallheaders();
$session_id = $headers['X-Session-ID'] ?? '';
$token = $headers['X-Token'] ?? '';

$user = $auth->verifySession($session_id, $token);
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// RBAC Check - Admin only
if (!RBAC::canAccessActivityLogs($user['role'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Whitelist activity_type for security
    $activity_type = isset($_GET['activity_type']) ? trim($_GET['activity_type']) : null;
    $allowedTypes = [
        'LOGIN',
        'JOB_SUBMIT',
        'JOB_VIEW',
        'DATA_TRANSFER',
        'HMAC_VERIFY',
        'ROLE_CHANGE',
        'ROLE_CHANGE_DENIED',
        'USER_DELETE',
        'REGISTER',
        'LOG_ACCESS'
    ];
    
    if (!in_array($activity_type, $allowedTypes, true)) {
        $activity_type = null;
    }
    
    $filters = [
        'userid' => isset($_GET['userid']) ? intval($_GET['userid']) : null,
        'activity_type' => $activity_type
    ];
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    $result = $logger->getActivityLogs($filters, $limit, $offset);
    
    // Log that admin viewed the logs
    $logger->logActivityLogAccess($user['userid']);
    
    echo json_encode(['success' => true, 'logs' => $result['logs'], 'pagination' => $result['pagination']]);
}
