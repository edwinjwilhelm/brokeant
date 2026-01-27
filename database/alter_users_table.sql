-- Update users table to add payment tracking columns
ALTER TABLE users ADD COLUMN IF NOT EXISTS payment_status VARCHAR(50) DEFAULT 'pending' COMMENT 'pending, verified, failed';
ALTER TABLE users ADD COLUMN IF NOT EXISTS payment_date DATETIME COMMENT 'When payment was completed';
ALTER TABLE users ADD COLUMN IF NOT EXISTS payment_transaction_id VARCHAR(255) COMMENT 'Reference to payments table transaction_id';
ALTER TABLE users ADD INDEX idx_payment_status (payment_status);
