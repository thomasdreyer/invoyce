-- Complete migration script to add all missing columns
-- Run this to update your existing database

-- Add discount columns to invoices table
ALTER TABLE invoices 
ADD COLUMN IF NOT EXISTS discount_percent DECIMAL(5,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10,2) DEFAULT 0.00;

-- Add discount columns to invoice_items table
ALTER TABLE invoice_items 
ADD COLUMN IF NOT EXISTS discount_percent DECIMAL(5,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10,2) DEFAULT 0.00;

-- Add sharing columns to invoices table
ALTER TABLE invoices 
ADD COLUMN IF NOT EXISTS share_token VARCHAR(64) UNIQUE,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add index for share_token if it doesn't exist
CREATE INDEX IF NOT EXISTS idx_share_token ON invoices(share_token);

-- Generate share tokens for existing invoices that don't have one
UPDATE invoices 
SET share_token = SHA2(CONCAT(id, created_at, RAND()), 256) 
WHERE share_token IS NULL;
