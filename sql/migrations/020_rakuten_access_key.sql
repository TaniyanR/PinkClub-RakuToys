SET NAMES utf8mb4;

SET @column_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'api_credentials'
    AND COLUMN_NAME = 'access_key'
);
SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE api_credentials ADD COLUMN access_key VARCHAR(255) NOT NULL DEFAULT "" AFTER api_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
