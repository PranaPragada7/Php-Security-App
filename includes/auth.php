<?php
/**
 * Authentication Helper Functions
 * Secure Web Application - Authentication
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/crypt.php';
require_once __DIR__ . '/hmac.php';

class Auth {
    private $db;
    
    public function __construct($db = null) {
        $this->db = $db ?? getDB();
    }
    
    /**
     * Verify user credentials
     * @param string $username
     * @param string $password
     * @return array|false User data if successful, false otherwise
     */
    public function verifyCredentials($username, $password) {
        try {
            $stmt = $this->db->prepare("SELECT userid, username, password, email, name, role FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Remove password from returned data
                unset($user['password']);
                // Ensure role is set (default to 'guest' if not present)
                if (!isset($user['role']) || empty($user['role'])) {
                    $user['role'] = 'guest';
                }
                return $user;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Authentication error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create a new session
     * @param int $userid
     * @return array Session data (session_id, token, session_name)
     */
    public function createSession($userid) {
        try {
            $session_id = bin2hex(random_bytes(32));
            $token = bin2hex(random_bytes(32));
            $session_name = SESSION_NAME;
            $expires_at = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
            
            $stmt = $this->db->prepare("
                INSERT INTO sessions (session_id, userid, token, session_name, expires_at) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$session_id, $userid, $token, $session_name, $expires_at]);
            
            return [
                'session_id' => $session_id,
                'token' => $token,
                'session_name' => $session_name,
                'userid' => $userid,
                'expires_at' => $expires_at
            ];
        } catch (PDOException $e) {
            error_log("Session creation error: " . $e->getMessage());
            throw new Exception("Failed to create session");
        }
    }
    
    /**
     * Verify session token
     * @param string $session_id
     * @param string $token
     * @return array|false Session data if valid, false otherwise
     */
    public function verifySession($session_id, $token) {
        try {
            $stmt = $this->db->prepare("
                SELECT s.*, u.username, u.email, u.name, u.role 
                FROM sessions s 
                JOIN users u ON s.userid = u.userid 
                WHERE s.session_id = ? AND s.token = ? AND s.expires_at > NOW()
            ");
            $stmt->execute([$session_id, $token]);
            $session = $stmt->fetch();
            
            if ($session) {
                // Ensure role is set (default to 'guest' if not present)
                if (!isset($session['role']) || empty($session['role'])) {
                    $session['role'] = 'guest';
                }
            }
            
            return $session ? $session : false;
        } catch (PDOException $e) {
            error_log("Session verification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Register a new user
     * @param string $username
     * @param string $password
     * @param string $email
     * @param string $name
     * @param string $role User role (default: 'guest')
     * @return array|false User data if successful, false otherwise
     */
    public function registerUser($username, $password, $email, $name, $role = 'guest') {
        try {
            // Validate role
            require_once __DIR__ . '/rbac.php';
            if (!RBAC::isValidRole($role)) {
                $role = RBAC::getDefaultRole();
            }
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Generate HMAC for user data integrity (data at rest)
            $data_hmac = HMAC::generateForUser($username, $email, $name);
            
            $stmt = $this->db->prepare("
                INSERT INTO users (username, password, email, name, role, data_hmac) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $hashed_password, $email, $name, $role, $data_hmac]);
            
            $userid = $this->db->lastInsertId();
            
            return [
                'userid' => $userid,
                'username' => $username,
                'email' => $email,
                'name' => $name,
                'role' => $role
            ];
        } catch (PDOException $e) {
            error_log("User registration error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user role by user ID
     * @param int $userid
     * @return string|false User role or false if not found
     */
    public function getUserRole($userid) {
        try {
            $stmt = $this->db->prepare("SELECT role FROM users WHERE userid = ?");
            $stmt->execute([$userid]);
            $result = $stmt->fetch();
            
            return $result ? ($result['role'] ?? 'guest') : false;
        } catch (PDOException $e) {
            error_log("Error retrieving user role: " . $e->getMessage());
            return false;
        }
    }
}

