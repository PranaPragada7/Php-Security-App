<?php
/**
 * Database connection singleton class
 * Provides PDO database connection via getDB() helper function
 */

require_once __DIR__ . '/settings.php';

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            global $dsn, $db_config, $pdo_options;
            $this->connection = new PDO($dsn, $db_config['username'], $db_config['password'], $pdo_options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please check your configuration.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Helper function to get database connection
function getDB() {
    return Database::getInstance()->getConnection();
}

