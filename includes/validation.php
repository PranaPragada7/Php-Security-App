<?php
/**
 * Input Validation Helper Functions
 * Secure Web Application - Input Validation
 */

class Validator {
    /**
     * Validate username
     * @param string $username
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateUsername($username) {
        if (empty($username)) {
            return ['valid' => false, 'error' => 'Username is required'];
        }
        
        $username = trim($username);
        
        if (strlen($username) < 3) {
            return ['valid' => false, 'error' => 'Username must be at least 3 characters'];
        }
        
        if (strlen($username) > 50) {
            return ['valid' => false, 'error' => 'Username must be no more than 50 characters'];
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return ['valid' => false, 'error' => 'Username can only contain letters, numbers, and underscores'];
        }
        
        return ['valid' => true, 'error' => null];
    }
    
    /**
     * Validate password
     * @param string $password
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validatePassword($password) {
        if (empty($password)) {
            return ['valid' => false, 'error' => 'Password is required'];
        }
        
        if (strlen($password) < 10) {
            return ['valid' => false, 'error' => 'Password must be at least 10 characters'];
        }
        
        // Additional password strength checks (optional)
        if (strlen($password) > 255) {
            return ['valid' => false, 'error' => 'Password is too long'];
        }
        
        return ['valid' => true, 'error' => null];
    }
    
    /**
     * Validate job name
     * @param string $job_name
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateJobName($job_name) {
        if (empty($job_name)) {
            return ['valid' => false, 'error' => 'Job name is required'];
        }
        
        $job_name = trim($job_name);
        
        if (strlen($job_name) < 1) {
            return ['valid' => false, 'error' => 'Job name cannot be empty'];
        }
        
        if (strlen($job_name) > 255) {
            return ['valid' => false, 'error' => 'Job name must be no more than 255 characters'];
        }
        
        // Allow most printable characters, but prevent injection patterns
        if (preg_match('/[<>"\']/', $job_name)) {
            return ['valid' => false, 'error' => 'Job name contains invalid characters'];
        }
        
        return ['valid' => true, 'error' => null];
    }
    
    /**
     * Validate OPN number (basic validation - length and trim)
     * @param string $opn_number
     * @return array ['valid' => bool, 'error' => string|null, 'value' => string]
     */
    public static function validateOpnNumber($opn_number) {
        if (empty($opn_number)) {
            return ['valid' => false, 'error' => 'OPN number is required', 'value' => ''];
        }
        
        $opn_number = trim($opn_number);
        
        if (strlen($opn_number) < 1) {
            return ['valid' => false, 'error' => 'OPN number cannot be empty', 'value' => ''];
        }
        
        if (strlen($opn_number) > 1000) {
            return ['valid' => false, 'error' => 'OPN number is too long (max 1000 characters)', 'value' => ''];
        }
        
        return ['valid' => true, 'error' => null, 'value' => $opn_number];
    }
    
    /**
     * Validate email (basic format check)
     * @param string $email
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateEmail($email) {
        if (empty($email)) {
            return ['valid' => false, 'error' => 'Email is required'];
        }
        
        $email = trim($email);
        
        if (strlen($email) > 100) {
            return ['valid' => false, 'error' => 'Email is too long'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'error' => 'Invalid email format'];
        }
        
        return ['valid' => true, 'error' => null];
    }
    
    /**
     * Validate name
     * @param string $name
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateName($name) {
        if (empty($name)) {
            return ['valid' => false, 'error' => 'Name is required'];
        }
        
        $name = trim($name);
        
        if (strlen($name) < 1) {
            return ['valid' => false, 'error' => 'Name cannot be empty'];
        }
        
        if (strlen($name) > 100) {
            return ['valid' => false, 'error' => 'Name must be no more than 100 characters'];
        }
        
        return ['valid' => true, 'error' => null];
    }
}
