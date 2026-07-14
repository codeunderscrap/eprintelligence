CREATE DATABASE IF NOT EXISTS epr_dashboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE epr_dashboard;

-- Users table for role‑based access
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Projects table (each upload belongs to a project)
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    owner_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Companies (rows from CSV/Excel)
CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    registration_number VARCHAR(100),
    state VARCHAR(100),
    battery_chemistry VARCHAR(100),
    target_tons DECIMAL(12,2),
    credits DECIMAL(12,2),
    import_quantity DECIMAL(12,2),
    other_json JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project_company (project_id, company_name)
) ENGINE=InnoDB;

-- Research results (Tavily raw + Groq JSON)
CREATE TABLE IF NOT EXISTS research (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    tavily_raw JSON,
    groq_json JSON,
    priority_score DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Optional table for scoring weights (admin can adjust)
CREATE TABLE IF NOT EXISTS priority_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric VARCHAR(50) NOT NULL,
    weight DECIMAL(5,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(metric)
) ENGINE=InnoDB;
