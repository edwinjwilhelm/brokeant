-- Create payments table for Phase 4
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL COMMENT 'Amount in CAD',
    currency VARCHAR(3) DEFAULT 'CAD',
    gateway VARCHAR(20) NOT NULL COMMENT 'stripe or paypal',
    transaction_id VARCHAR(255) NOT NULL UNIQUE COMMENT 'Stripe charge ID or PayPal transaction ID',
    status VARCHAR(50) DEFAULT 'pending' COMMENT 'pending, succeeded, failed, refunded',
    payment_method VARCHAR(50) COMMENT 'card, paypal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_transaction_id (transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
