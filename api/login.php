<?php
// Login API endpoint - Authenticates users and creates sessions

header('Content-Type: application/json');
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/validation.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!csrf_validate_request()) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF token validation failed']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['username']) || !isset($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing credentials']);
        exit;
    }

    // Check rate limit
    $rateLimiter = new RateLimiter();
    $rateLimit = $rateLimiter->checkLimit('login', 5, 600); // 5 attempts per 10 minutes
    
    if (!$rateLimit['allowed']) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many login attempts. Please try again later.']);
        exit;
    }

    // Validate input
    $username_validation = Validator::validateUsername($input['username']);
    $password_validation = Validator::validatePassword($input['password']);
    
    if (!$username_validation['valid']) {
        http_response_code(400);
        echo json_encode(['error' => $username_validation['error']]);
        exit;
    }
    
    if (!$password_validation['valid']) {
        http_response_code(400);
        echo json_encode(['error' => $password_validation['error']]);
        exit;
    }

    try {
        $db = getDB();
        $auth = new Auth($db);
        $logger = new ActivityLogger();
        
        $user = $auth->verifyCredentials($input['username'], $input['password']);
        
        if ($user) {
            // Reset rate limit on successful login
            $rateLimiter->resetLimit('login');
            
            $session_data = $auth->createSession($user['userid']);
            
            // Regenerate session ID for security
            session_regenerate_secure(true);
            
            // Log successful login
            $logger->logLogin($user['userid'], $user['username']);
            
            // Get role permissions
            $role = $user['role'] ?? RBAC::ROLE_GUEST;
            $permissions = RBAC::getRolePermissions($role);
            
            echo json_encode([
                'success' => true,
                'session_id' => $session_data['session_id'],
                'token' => $session_data['token'],
                'user' => [
                    'userid' => $user['userid'],
                    'username' => $user['username'],
                    'name' => $user['name'],
                    'role' => $role
                ],
                'permissions' => $permissions
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        http_response_code(500);
        $error_msg = (defined('APP_ENV') && APP_ENV === 'production') 
            ? 'Server error' 
            : 'Server error: ' . $e->getMessage();
        echo json_encode(['error' => $error_msg]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
