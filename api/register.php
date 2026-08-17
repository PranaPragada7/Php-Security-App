<?php
declare(strict_types=1);
// Registration API endpoint - Creates new user accounts with password hashing and HMAC

header('Content-Type: application/json');
// Enable error reporting for debugging
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/validation.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate_request()) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF token validation failed']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        exit;
    }

    $rateLimiter = new RateLimiter();
    $rateLimit = $rateLimiter->checkLimit('register', 3, 600);
    if (!$rateLimit['allowed']) {
        $available = $rateLimit['available'] ?? true;
        http_response_code($available ? 429 : 503);
        echo json_encode(['error' => $available
            ? 'Too many registration attempts. Please try again later.'
            : 'Registration is temporarily unavailable.']);
        exit;
    }

    // Validate input
    if (!isset($input['username']) || !isset($input['password']) || !isset($input['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }

    $username = trim($input['username']);
    $password = $input['password'];
    // Auto-generate email if not provided (email field removed from UI but DB requires it)
    $email = isset($input['email']) ? trim($input['email']) : $username . '@example.test';
    $name = trim($input['name']);
    // Public registration never accepts a privileged role from the client.
    $role = RBAC::ROLE_USER;

    foreach ([
        Validator::validateUsername($username),
        Validator::validatePassword($password),
        Validator::validateName($name),
        Validator::validateEmail($email),
    ] as $validation) {
        if (!$validation['valid']) {
            http_response_code(400);
            echo json_encode(['error' => $validation['error']]);
            exit;
        }
    }

    try {
        $db = getDB();
        
        // Return a clear conflict response before attempting the insert.
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(['error' => "Username '$username' is already taken"]);
            exit;
        }

        // Initialize Auth
        $auth = new Auth($db);
        $logger = new ActivityLogger();

        // Register user
        $result = $auth->registerUser($username, $password, $email, $name, $role);
        
        if ($result) {
            // Reset rate limit on successful registration
            $rateLimiter->resetLimit('register');
            
            $userid = $result['userid'];
            
            try {
                $logger->logRegistration($userid, $username, $role);
            } catch (Exception $logEx) {
                error_log("Logging failed during registration: " . $logEx->getMessage());
            }
            
            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'User registered successfully']);
        } else {
            // Capture specific error from PDO if possible
            http_response_code(500);
            $error_msg = (defined('APP_ENV') && APP_ENV === 'production') 
                ? 'Registration failed. Please try again.' 
                : 'Registration returned false. Check server logs.';
            echo json_encode(['error' => $error_msg]);
        }

    } catch (PDOException $e) {
        error_log("Registration database error: " . $e->getMessage());
        http_response_code(500);
        $error_msg = (defined('APP_ENV') && APP_ENV === 'production') 
            ? 'Database error occurred' 
            : 'Database error: ' . $e->getMessage();
        echo json_encode(['error' => $error_msg]);
    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        http_response_code(500);
        $error_msg = (defined('APP_ENV') && APP_ENV === 'production') 
            ? 'Server error occurred' 
            : 'Server error: ' . $e->getMessage();
        echo json_encode(['error' => $error_msg]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
