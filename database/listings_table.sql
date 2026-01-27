-- Create listings table for BrokeAnt Marketplace
-- Run this after users table is created

USE brokeant;

CREATE TABLE listings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  price DECIMAL(10, 2),
  category VARCHAR(50),
  image_url VARCHAR(500),
  status ENUM('active', 'sold', 'removed', 'expired') DEFAULT 'active',
  city VARCHAR(50) NOT NULL,
  posted_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expiration_date DATETIME,
  last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  views INT DEFAULT 0,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_city (city),
  INDEX idx_status (status),
  INDEX idx_posted_date (posted_date)
);
