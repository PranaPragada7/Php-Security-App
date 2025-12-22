
-- Database Migration Script for RBAC, HMAC, and Activity Monitoring
-- Run this script on existing databases to add new features
-- Database: encryption_demo_server

USE encryption_demo_server;

-- Add role column to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS role ENUM('admin', 'user', 'guest') DEFAULT 'guest' AFTER name;

-- Add index on role column
ALTER TABLE users 
ADD INDEX IF NOT EXISTS idx_role (role);

-- Update existing users with default 'guest' role (if role column was just added)
UPDATE users SET role = 'guest' WHERE role IS NULL OR role = '';

-- Set one user as 'admin' for testing (update username as needed)
UPDATE users SET role = 'admin' WHERE username = 'admin' LIMIT 1;

-- Add clear_text_data column to jobs table
ALTER TABLE jobs 
ADD COLUMN IF NOT EXISTS clear_text_data TEXT AFTER opn_number_encrypted;

-- Add data_hmac column to jobs table
ALTER TABLE jobs 
ADD COLUMN IF NOT EXISTS data_hmac VARCHAR(255) NOT NULL DEFAULT '' AFTER clear_text_data;

-- Add index on data_hmac column
ALTER TABLE jobs 
ADD INDEX IF NOT EXISTS idx_data_hmac (data_hmac);

-- Update existing jobs with empty HMAC (for migration)
-- Note: You may want to regenerate HMAC for existing jobs using a script
UPDATE jobs SET data_hmac = '' WHERE data_hmac = '' OR data_hmac IS NULL;

-- Add data_hmac column to users table for user data integrity
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS data_hmac VARCHAR(255) NULL AFTER role;

-- Add index on users data_hmac column
ALTER TABLE users 
ADD INDEX IF NOT EXISTS idx_users_data_hmac (data_hmac);

-- Create activity_log table if it doesn't exist
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

-- Verification queries (optional - uncomment to check migration)
-- SELECT COUNT(*) as total_users, role, COUNT(*) as count FROM users GROUP BY role;
-- SELECT COUNT(*) as jobs_with_hmac FROM jobs WHERE data_hmac IS NOT NULL AND data_hmac != '';
-- SELECT COUNT(*) as total_logs FROM activity_log;

