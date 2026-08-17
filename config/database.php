<?php
declare(strict_types=1);
/**
 * Database connection singleton class
 * Provides PDO database connection via getDB() helper function
 */

require_once __DIR__ . '/bootstrap.php';

final class Database {
    private static ?self $instance = null;
    private PDO $connection;
    
    private function __construct() {
        try {
            global $dsn, $db_config, $pdo_options;
            $this->connection = new PDO($dsn, $db_config['username'], $db_config['password'], $pdo_options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please check your configuration.");
        }
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection(): PDO {
        return $this->connection;
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup(): void {
        throw new LogicException('Cannot unserialize the database singleton.');
    }
}

// Helper function to get database connection
function getDB(): PDO {
    return Database::getInstance()->getConnection();
}

