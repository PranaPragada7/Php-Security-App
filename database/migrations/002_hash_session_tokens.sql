-- Existing application sessions cannot be converted because only their raw
-- bearer tokens were stored. Revoke them before deploying the hashed-token
-- release so every user receives a newly protected session.
TRUNCATE TABLE sessions;
ALTER TABLE sessions MODIFY token CHAR(64) NOT NULL COMMENT 'SHA-256 hash of the issued session token';
