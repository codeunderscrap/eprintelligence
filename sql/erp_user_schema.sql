-- ERP User Database Schema

CREATE DATABASE IF NOT EXISTS erp_user;
USE erp_user;

-- Users table (admin / regular users)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Projects table (grouping of uploads)
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Companies (uploaded data) – generic columns to match the Excel file
CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    registration_number VARCHAR(100),
    company_name VARCHAR(255),
    target_tons DECIMAL(12,2),
    credits DECIMAL(12,2),
    other_json JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Priority rule weighting (defaults: tons = 1, credits = 0.5)
CREATE TABLE IF NOT EXISTS priority_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric VARCHAR(50) NOT NULL,
    weight DECIMAL(5,2) NOT NULL,
    UNIQUE(metric)
) ENGINE=InnoDB;

INSERT IGNORE INTO priority_rules (metric, weight) VALUES
    ('target_tons', 1.00),
    ('credits', 0.50);

-- Example admin user (password: admin123)
INSERT IGNORE INTO users (username, password_hash, role) VALUES (
    'admin',
    '$2y$10$VhKQ9J9e7UOj3ZcVhZ0E9eG9lIhK5nV/0K6cR9zqL1t2Z8kWJlG9a',
    'admin'
);
