-- Migration script to add discount functionality
-- Run this to update existing database

ALTER TABLE invoice_items 
ADD COLUMN discount_percent DECIMAL(5,2) DEFAULT 0.00,
ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0.00;

ALTER TABLE invoices 
ADD COLUMN discount_percent DECIMAL(5,2) DEFAULT 0.00,
ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0.00;
