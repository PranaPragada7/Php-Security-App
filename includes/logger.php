<?php
// Activity logger - Logs user actions and security events to database

require_once __DIR__ . '/../config/settings.php';

class ActivityLogger {
    private $db;

    public function __construct() {
        $this->connect();
    }

    private function connect() {
        try {
            require_once __DIR__ . '/database.php';
            $this->db = getDB();
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Fail silently for logging
        }
    }

    public function log($userid, $activity_type, $description, $ip_address = null, $user_agent = null) {
        if (!defined('ACTIVITY_LOGGING_ENABLED') || !ACTIVITY_LOGGING_ENABLED) {
            return false;
        }

        if (!$this->db) {
            return false;
        }

        try {
            if ($ip_address === null) {
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            }
            
            if ($user_agent === null) {
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            }

            $stmt = $this->db->prepare("
                INSERT INTO activity_log 
                (userid, activity_type, description, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $userid, 
                $activity_type, 
                $description, 
                $ip_address, 
                $user_agent
            ]);
        } catch (Exception $e) {
            return false;
        }
    }

    public function logLogin($userid, $username) {
        return $this->log($userid, 'LOGIN', "User '{$username}' logged in successfully");
    }

    public function logDataTransfer($userid, $data_type, $description) {
        return $this->log($userid, 'DATA_TRANSFER', "{$data_type}: {$description}");
    }

    public function logJobSubmission($userid, $jobid, $job_name) {
        return $this->log($userid, 'JOB_SUBMIT', "Job ID {$jobid} '{$job_name}' submitted");
    }

    public function logJobView($userid, $role) {
        return $this->log($userid, 'JOB_VIEW', "Jobs viewed (role: {$role})");
    }

    public function logHMACVerification($userid, $success, $description) {
        $status = $success ? 'Successful' : 'Failed';
        return $this->log($userid, 'HMAC_VERIFY', "{$status}: {$description}");
    }
    
    public function logRegistration($userid, $username, $role) {
        return $this->log($userid, 'REGISTER', "New user '{$username}' registered with role '{$role}'");
    }
    
    public function logActivityLogAccess($userid) {
        return $this->log($userid, 'LOG_ACCESS', "Accessed activity logs");
    }

    public function getActivityLogs($filters = [], $limit = 100, $offset = 0) {
        if (!$this->db) return [];
        
        try {
            $query = "
                SELECT l.*, u.username, u.role 
                FROM activity_log l
                LEFT JOIN users u ON l.userid = u.userid
                WHERE 1=1
            ";
            $params = [];
            
            if (isset($filters['userid']) && !empty($filters['userid'])) {
                $query .= " AND l.userid = ?";
                $params[] = $filters['userid'];
            }
            
            if (isset($filters['activity_type']) && !empty($filters['activity_type'])) {
                $query .= " AND l.activity_type = ?";
                $params[] = $filters['activity_type'];
            }
            
            // Count total
            $countQuery = str_replace("l.*, u.username, u.role", "COUNT(*)", $query);
            $stmt = $this->db->prepare($countQuery);
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            
            // Get data
            $query .= " ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
            $stmt = $this->db->prepare($query);
            
            // Bind parameters manually for limit/offset (PDO quirk)
            foreach ($params as $k => $v) {
                $stmt->bindValue($k + 1, $v);
            }
            $stmt->bindValue(count($params) + 1, (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(count($params) + 2, (int)$offset, PDO::PARAM_INT);
            
            $stmt->execute();
            
            return [
                'logs' => $stmt->fetchAll(),
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total
                ]
            ];
        } catch (Exception $e) {
            return ['logs' => [], 'pagination' => []];
        }
    }
}
