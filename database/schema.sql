-- CipherDesk database schema

CREATE DATABASE IF NOT EXISTS cipherdesk;
USE cipherdesk;

-- Users table for storing user credentials
CREATE TABLE IF NOT EXISTS users (
    userid INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user', 'guest') DEFAULT 'guest',
    data_hmac VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_users_data_hmac (data_hmac)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Jobs table for storing encrypted OPN numbers and job information
CREATE TABLE IF NOT EXISTS jobs (
    jobid INT AUTO_INCREMENT PRIMARY KEY,
    userid INT NOT NULL,
    job_name VARCHAR(255) NOT NULL,
    opn_number_encrypted TEXT NOT NULL,
    clear_text_data TEXT,
    data_hmac VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (userid) REFERENCES users(userid) ON DELETE CASCADE,
    INDEX idx_userid (userid),
    INDEX idx_data_hmac (data_hmac)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sessions table for managing user sessions
CREATE TABLE IF NOT EXISTS sessions (
    session_id VARCHAR(128) PRIMARY KEY,
    userid INT NOT NULL,
    token CHAR(64) NOT NULL COMMENT 'SHA-256 hash of the issued session token',
    session_name VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (userid) REFERENCES users(userid) ON DELETE CASCADE,
    INDEX idx_userid (userid),
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Persistent authentication throttling
CREATE TABLE IF NOT EXISTS auth_rate_limits (
    ip VARCHAR(45) NOT NULL,
    action VARCHAR(50) NOT NULL,
    attempts INT NOT NULL DEFAULT 1,
    first_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ip, action),
    INDEX idx_last_attempt (last_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activity log table for monitoring user activities
CREATE TABLE IF NOT EXISTS activity_log (
    logid INT AUTO_INCREMENT PRIMARY KEY,
    userid INT NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (userid) REFERENCES users(userid) ON DELETE CASCADE,
    INDEX idx_userid (userid),
    INDEX idx_activity_type (activity_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

