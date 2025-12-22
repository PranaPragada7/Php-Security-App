<?php
/**
 * Rate Limiting Helper
 * Secure Web Application - Rate Limiting
 */

require_once __DIR__ . '/../config/database.php';

class RateLimiter {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
        $this->initTable();
    }
    
    /**
     * Initialize rate limit table if it doesn't exist
     */
    private function initTable() {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS auth_rate_limits (
                    ip VARCHAR(45) NOT NULL,
                    action VARCHAR(50) NOT NULL,
                    attempts INT NOT NULL DEFAULT 1,
                    first_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    last_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (ip, action),
                    INDEX idx_last_attempt (last_attempt_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (PDOException $e) {
            // Table may already exist, ignore
            error_log("Rate limit table init: " . $e->getMessage());
        }
    }
    
    /**
     * Get client IP address
     * @return string IP address
     */
    private function getClientIp() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Check if action is rate limited
     * @param string $action Action name (e.g., 'login', 'register')
     * @param int $max_attempts Maximum attempts allowed
     * @param int $window_seconds Time window in seconds
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_at' => timestamp]
     */
    public function checkLimit($action, $max_attempts, $window_seconds) {
        $ip = $this->getClientIp();
        
        try {
            // Get current rate limit record
            $stmt = $this->db->prepare("
                SELECT attempts, first_attempt_at, last_attempt_at 
                FROM auth_rate_limits 
                WHERE ip = ? AND action = ?
            ");
            $stmt->execute([$ip, $action]);
            $record = $stmt->fetch();
            
            if ($record) {
                $first_attempt = strtotime($record['first_attempt_at']);
                $now = time();
                $elapsed = $now - $first_attempt;
                
                // Reset if window has passed
                if ($elapsed >= $window_seconds) {
                    // Delete old record
                    $delete_stmt = $this->db->prepare("DELETE FROM auth_rate_limits WHERE ip = ? AND action = ?");
                    $delete_stmt->execute([$ip, $action]);
                    
                    // Create new record
                    $insert_stmt = $this->db->prepare("
                        INSERT INTO auth_rate_limits (ip, action, attempts, first_attempt_at, last_attempt_at)
                        VALUES (?, ?, 1, NOW(), NOW())
                    ");
                    $insert_stmt->execute([$ip, $action]);
                    
                    return [
                        'allowed' => true,
                        'remaining' => $max_attempts - 1,
                        'reset_at' => $now + $window_seconds
                    ];
                }
                
                // Check if limit exceeded
                if ($record['attempts'] >= $max_attempts) {
                    $reset_at = $first_attempt + $window_seconds;
                    return [
                        'allowed' => false,
                        'remaining' => 0,
                        'reset_at' => $reset_at
                    ];
                }
                
                // Increment attempts
                $update_stmt = $this->db->prepare("
                    UPDATE auth_rate_limits 
                    SET attempts = attempts + 1, last_attempt_at = NOW()
                    WHERE ip = ? AND action = ?
                ");
                $update_stmt->execute([$ip, $action]);
                
                $remaining = $max_attempts - ($record['attempts'] + 1);
                return [
                    'allowed' => true,
                    'remaining' => max(0, $remaining),
                    'reset_at' => $first_attempt + $window_seconds
                ];
            } else {
                // First attempt - create record
                $insert_stmt = $this->db->prepare("
                    INSERT INTO auth_rate_limits (ip, action, attempts, first_attempt_at, last_attempt_at)
                    VALUES (?, ?, 1, NOW(), NOW())
                ");
                $insert_stmt->execute([$ip, $action]);
                
                return [
                    'allowed' => true,
                    'remaining' => $max_attempts - 1,
                    'reset_at' => time() + $window_seconds
                ];
            }
        } catch (PDOException $e) {
            // On error, allow request (fail open for availability)
            error_log("Rate limit check error: " . $e->getMessage());
            return [
                'allowed' => true,
                'remaining' => $max_attempts,
                'reset_at' => time() + $window_seconds
            ];
        }
    }
    
    /**
     * Reset rate limit for an action (e.g., on successful login)
     * @param string $action Action name
     */
    public function resetLimit($action) {
        $ip = $this->getClientIp();
        try {
            $stmt = $this->db->prepare("DELETE FROM auth_rate_limits WHERE ip = ? AND action = ?");
            $stmt->execute([$ip, $action]);
        } catch (PDOException $e) {
            error_log("Rate limit reset error: " . $e->getMessage());
        }
    }
}
