-- Upgrade an early CipherDesk database with RBAC, integrity, and audit fields.
USE cipherdesk;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS role ENUM('admin', 'user', 'guest') DEFAULT 'guest' AFTER name,
    ADD COLUMN IF NOT EXISTS data_hmac VARCHAR(255) NULL AFTER role;

UPDATE users SET role = 'guest' WHERE role IS NULL OR role = '';

ALTER TABLE jobs
    ADD COLUMN IF NOT EXISTS clear_text_data TEXT AFTER opn_number_encrypted,
    ADD COLUMN IF NOT EXISTS data_hmac VARCHAR(255) NOT NULL DEFAULT '' AFTER clear_text_data;

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

-- Provision administrators with scripts/create_admin.php. After applying the
-- migrations, run scripts/rehash_integrity.php to sign existing records.
