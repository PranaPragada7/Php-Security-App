-- Migration: Remove plaintext OPN storage column for security
-- Date: 2024
-- Description: Removes opn_number_plaintext column from jobs table
--              to ensure sensitive data is only stored encrypted

-- Check if column exists before dropping (for MySQL 8.0+)
-- For older MySQL versions, run: ALTER TABLE jobs DROP COLUMN opn_number_plaintext;

SET @dbname = DATABASE();
SET @tablename = "jobs";
SET @columnname = "opn_number_plaintext";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "ALTER TABLE jobs DROP COLUMN opn_number_plaintext;",
  "SELECT 'Column opn_number_plaintext does not exist in jobs table.' AS message;"
));
PREPARE alterIfExists FROM @preparedStatement;
EXECUTE alterIfExists;
DEALLOCATE PREPARE alterIfExists;

-- Alternative simpler version (uncomment if above doesn't work):
-- ALTER TABLE jobs DROP COLUMN IF EXISTS opn_number_plaintext;
