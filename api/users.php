<?php
declare(strict_types=1);
// Users API endpoint - User management (list, delete, change roles - root-only)

header('Content-Type: application/json');
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/hmac.php';
require_once __DIR__ . '/../includes/request_auth.php';

// Verify session and role
try {
    require_once __DIR__ . '/../config/database.php';
    $db = getDB();
    $auth = new Auth($db);
    $logger = new ActivityLogger();
    
    $session = authenticated_request_user($auth);
    
    if (!$session) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired session']);
        exit;
    }
    
    // Get user role from session (RBAC logic included in verifySession result in some implementations, but let's be safe)
    // verifySession returns role in the session array if Auth.php includes it.
    // Based on previous read of Auth.php, it joins users table, so 'role' is present.
    $user_role = $session['role'] ?? 'guest';
    
    // Check permission
    if (!RBAC::canManageUsers($user_role)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden: Admin access required']);
        exit;
    }

    // Handle GET request (List users)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->prepare("SELECT userid, username, email, name, role, data_hmac, created_at FROM users ORDER BY created_at DESC");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Verify HMAC for each user (data integrity check)
        foreach ($users as &$user) {
            $user['hmac_verified'] = false;
            $user['hmac_status'] = 'not_checked';
            
            if (!empty($user['data_hmac'])) {
                // Verify HMAC for user data
                $isValid = HMAC::verifyUser($user['username'], $user['email'], $user['name'], $user['data_hmac']);
                $user['hmac_verified'] = $isValid;
                $user['hmac_status'] = $isValid ? 'verified' : 'failed';
                
                // Log HMAC verification failure
                if (!$isValid) {
                    $logger->logHMACVerification(
                        $session['userid'], 
                        false, 
                        "User data integrity check failed for user '{$user['username']}' (ID: {$user['userid']})"
                    );
                }
            } else {
                // No HMAC stored (legacy data)
                $user['hmac_status'] = 'not_available';
            }
            
            // Remove data_hmac from response for security (only send verification status)
            unset($user['data_hmac']);
        }
        unset($user); // Break reference
        
        echo json_encode(['users' => $users]);
    }
    
    // Handle PUT/PATCH request (Update user role)
    elseif ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
        // Validate CSRF token for state-changing operations
        if (!csrf_validate_request()) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF token validation failed']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON input']);
            exit;
        }
        
        if (!isset($input['userid']) || !isset($input['role'])) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID and role are required']);
            exit;
        }
        
        $target_userid = intval($input['userid']);
        $new_role = trim($input['role']);
        
        // Validate role
        if (!RBAC::isValidRole($new_role)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid role. Must be admin, user, or guest']);
            exit;
        }
        
        // Get current user info
        $current_username = $session['username'] ?? '';
        
        // Check if target user exists
        $check = $db->prepare("SELECT userid, username, role FROM users WHERE userid = ?");
        $check->execute([$target_userid]);
        $target_user = $check->fetch();
        
        if (!$target_user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }
        
        // Check if role is actually changing
        if ($target_user['role'] === $new_role) {
            http_response_code(400);
            echo json_encode(['error' => 'User already has this role']);
            exit;
        }
        
        // Check permission: only root can change roles (this also prevents changing root's own role)
        $current_user = ['username' => $current_username];
        if (!RBAC::canChangeRole($current_user, $target_user)) {
            // Log denied attempt
            $logger->log(
                $session['userid'],
                'ROLE_CHANGE_DENIED',
                "Attempted to change role of user '{$target_user['username']}' (ID: {$target_userid}) from '{$target_user['role']}' to '{$new_role}' - Access denied (not root user or attempting to change root)",
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );
            
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Only root user can change roles']);
            exit;
        }
        
        $old_role = $target_user['role'];
        
        // Update role
        $stmt = $db->prepare("UPDATE users SET role = ? WHERE userid = ?");
        if ($stmt->execute([$new_role, $target_userid])) {
            // Log successful role change
            $logger->log(
                $session['userid'],
                'ROLE_CHANGE',
                "Changed role of user '{$target_user['username']}' (ID: {$target_userid}) from '{$old_role}' to '{$new_role}'",
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );
            
            echo json_encode([
                'success' => true,
                'message' => "User role changed from '{$old_role}' to '{$new_role}'",
                'userid' => $target_userid,
                'old_role' => $old_role,
                'new_role' => $new_role
            ]);
        } else {
            error_log("Failed to update user role for userid: $target_userid");
            http_response_code(500);
            $error_msg = (defined('APP_ENV') && APP_ENV === 'production') 
                ? 'Failed to update user role' 
                : 'Failed to update user role';
            echo json_encode(['error' => $error_msg]);
        }
    }
    
    // Handle DELETE request (Delete user)
    elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Validate CSRF token for state-changing operations
        if (!csrf_validate_request()) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF token validation failed']);
            exit;
        }
        
        // Parse ID from query string or body
        $delete_id = isset($_GET['id']) ? intval($_GET['id']) : null;
        
        if (!$delete_id) {
            // Try to get from body if not in query string
            $input = json_decode(file_get_contents('php://input'), true);
            $delete_id = isset($input['id']) ? intval($input['id']) : null;
        }

        if (!$delete_id) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID is required']);
            exit;
        }

        // Prevent self-deletion
        if ($delete_id == $session['userid']) {
            http_response_code(400);
            echo json_encode(['error' => 'You cannot delete your own account']);
            exit;
        }
        
        // Check if user exists
        $check = $db->prepare("SELECT username FROM users WHERE userid = ?");
        $check->execute([$delete_id]);
        $target_user = $check->fetch();
        
        if (!$target_user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        // Prevent deletion of Root User
        if (defined('ROOT_USERNAME') && $target_user['username'] === ROOT_USERNAME) {
            http_response_code(403);
            echo json_encode(['error' => 'Cannot delete the Root User account']);
            exit;
        }

        // Execute delete (Cascades to jobs, sessions, activity_logs)
        $stmt = $db->prepare("DELETE FROM users WHERE userid = ?");
        if ($stmt->execute([$delete_id])) {
            // Log the action
            $logger->log(
                $session['userid'], 
                'USER_DELETE', 
                "Deleted user '{$target_user['username']}' (ID: $delete_id)", 
                $_SERVER['REMOTE_ADDR'] ?? null, 
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );
            
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        } else {
            error_log("Failed to delete user: $delete_id");
            http_response_code(500);
            $error_msg = (defined('APP_ENV') && APP_ENV === 'production') 
                ? 'Failed to delete user' 
                : 'Failed to delete user';
            echo json_encode(['error' => $error_msg]);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    error_log("Users API error: " . $e->getMessage());
    http_response_code(500);
    $error_msg = (defined('APP_ENV') && APP_ENV === 'production') 
        ? 'Server error occurred' 
        : 'Server error: ' . $e->getMessage();
    echo json_encode(['error' => $error_msg]);
}
