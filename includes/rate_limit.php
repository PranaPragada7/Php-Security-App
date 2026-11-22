<?php
declare(strict_types=1);
/**
 * Rate Limiting Helper
 * Secure Web Application - Rate Limiting
 */

require_once __DIR__ . '/../config/database.php';

class RateLimiter {
    private $db;
    
    public function __construct(?PDO $db = null) {
        $this->db = $db ?? getDB();
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
    public function getClientIp(): string {
        $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!in_array($remoteAddress, TRUSTED_PROXIES, true)) {
            return filter_var($remoteAddress, FILTER_VALIDATE_IP) ? $remoteAddress : '0.0.0.0';
        }

        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $key) {
            foreach (explode(',', (string) ($_SERVER[$key] ?? '')) as $candidate) {
                $candidate = trim($candidate);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }

        return filter_var($remoteAddress, FILTER_VALIDATE_IP) ? $remoteAddress : '0.0.0.0';
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
        $transactionStarted = false;
        
        try {
            $this->db->beginTransaction();
            $transactionStarted = true;

            // Materialize the row even on the first request. The upsert takes
            // an InnoDB row lock, so competing requests for this key wait until
            // the decision and increment below have committed.
            $insert_stmt = $this->db->prepare("
                INSERT INTO auth_rate_limits (ip, action, attempts, first_attempt_at, last_attempt_at)
                VALUES (?, ?, 0, NOW(), NOW())
                ON DUPLICATE KEY UPDATE attempts = attempts
            ");
            $insert_stmt->execute([$ip, $action]);

            $stmt = $this->db->prepare("
                SELECT attempts, first_attempt_at, last_attempt_at 
                FROM auth_rate_limits 
                WHERE ip = ? AND action = ?
                FOR UPDATE
            ");
            $stmt->execute([$ip, $action]);
            $record = $stmt->fetch();
            if (!$record) {
                throw new PDOException('Rate limit record is unavailable');
            }

            $first_attempt = strtotime($record['first_attempt_at']);
            $now = time();
            $elapsed = $now - $first_attempt;

            if ($elapsed >= $window_seconds) {
                // Reset in place while retaining the same row lock. Deleting
                // and reinserting would create an unnecessary first-row race.
                $update_stmt = $this->db->prepare("
                    UPDATE auth_rate_limits
                    SET attempts = 1, first_attempt_at = NOW(), last_attempt_at = NOW()
                    WHERE ip = ? AND action = ?
                ");
                $update_stmt->execute([$ip, $action]);
                $result = [
                    'allowed' => true,
                    'remaining' => max(0, $max_attempts - 1),
                    'reset_at' => $now + $window_seconds
                ];
            } elseif ($record['attempts'] >= $max_attempts) {
                $result = [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset_at' => $first_attempt + $window_seconds
                ];
            } else {
                $update_stmt = $this->db->prepare("
                    UPDATE auth_rate_limits
                    SET attempts = attempts + 1, last_attempt_at = NOW()
                    WHERE ip = ? AND action = ?
                ");
                $update_stmt->execute([$ip, $action]);
                $result = [
                    'allowed' => true,
                    'remaining' => max(0, $max_attempts - ($record['attempts'] + 1)),
                    'reset_at' => $first_attempt + $window_seconds
                ];
            }

            $this->db->commit();
            return $result;
        } catch (PDOException $e) {
            if ($transactionStarted && $this->db->inTransaction()) {
                try {
                    $this->db->rollBack();
                } catch (PDOException $rollbackError) {
                    error_log('Rate limit rollback error: ' . $rollbackError->getMessage());
                }
            }
            // Authentication throttling fails closed when persistence is unavailable.
            error_log("Rate limit check error: " . $e->getMessage());
            return [
                'allowed' => false,
                'remaining' => 0,
                'reset_at' => time() + $window_seconds,
                'available' => false,
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
            // DELETE acquires the same InnoDB row lock as checkLimit's upsert,
            // and therefore waits for any in-flight decision on this key.
            $stmt = $this->db->prepare("DELETE FROM auth_rate_limits WHERE ip = ? AND action = ?");
            $stmt->execute([$ip, $action]);
        } catch (PDOException $e) {
            error_log("Rate limit reset error: " . $e->getMessage());
        }
    }
}
