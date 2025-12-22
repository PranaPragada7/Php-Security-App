-- Database Schema for Secure Web Application
-- Database: encryption_demo_server

CREATE DATABASE IF NOT EXISTS encryption_demo_server;
USE encryption_demo_server;

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

-- Login table (alternative/legacy table)
CREATE TABLE IF NOT EXISTS login (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username)
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
    token VARCHAR(255) NOT NULL,
    session_name VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (userid) REFERENCES users(userid) ON DELETE CASCADE,
    INDEX idx_userid (userid),
    INDEX idx_token (token)
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

