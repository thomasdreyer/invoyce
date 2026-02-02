-- Migration script to add sharing functionality
-- Run this to update existing database

ALTER TABLE invoices 
ADD COLUMN share_token VARCHAR(64) UNIQUE,
ADD INDEX idx_share_token (share_token);

-- Generate share tokens for existing invoices
UPDATE invoices SET share_token = SHA2(CONCAT(id, created_at, RAND()), 256) WHERE share_token IS NULL;
