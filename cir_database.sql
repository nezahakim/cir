-- ============================================================
-- Community Issue Reporter (CIR) Database
-- Case Study: Local Communities in Rwanda
-- ============================================================

CREATE DATABASE IF NOT EXISTS cir_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cir_db;

-- ============================================================
-- Table: users
-- Stores all system users (citizens and admins)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role ENUM('citizen', 'admin') NOT NULL DEFAULT 'citizen',
    profile_image VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- Table: categories
-- Issue categories with their severity weights for prioritization
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    severity_weight DECIMAL(3,1) NOT NULL COMMENT 'Weight used in priority score calculation (1.0 to 5.0)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- Table: issues
-- Stores all reported community issues
-- ============================================================
CREATE TABLE IF NOT EXISTS issues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,
    location_name VARCHAR(255) DEFAULT NULL,
    severity ENUM('Low', 'Medium', 'High', 'Critical') NOT NULL DEFAULT 'Medium',
    status ENUM('Pending', 'In Progress', 'Resolved') NOT NULL DEFAULT 'Pending',
    priority_score DECIMAL(5, 2) DEFAULT 0.00 COMMENT 'Calculated priority score for admin sorting',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

-- ============================================================
-- Table: notifications
-- Stores citizen notifications when admin updates issue status
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    issue_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- Table: issue_updates
-- Tracks all status changes made by admins
-- ============================================================
CREATE TABLE IF NOT EXISTS issue_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    issue_id INT NOT NULL,
    admin_id INT NOT NULL,
    update_message TEXT,
    old_status ENUM('Pending', 'In Progress', 'Resolved') NOT NULL,
    new_status ENUM('Pending', 'In Progress', 'Resolved') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- Seed Data: Categories with severity weights
-- ============================================================
INSERT INTO categories (category_name, severity_weight) VALUES
('Road Damage', 4.5),
('Flooding', 5.0),
('Broken Street Light', 3.0),
('Garbage Accumulation', 3.5),
('Water Leak', 4.0),
('Sewage Problem', 4.8),
('Bridge Damage', 5.0),
('Other', 2.0);

-- ============================================================
-- Seed Data: Admin user
-- Password: Admin@1234 (hashed with password_hash)
-- ============================================================
INSERT INTO users (full_name, email, password, phone, role) VALUES
('System Administrator', 'admin@cir.rw', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0780000001', 'admin');

-- NOTE: The password hash above is for 'password' — replace with a real hash in production.
-- To generate a proper hash for 'Admin@1234', run the system once and use the register page,
-- or use PHP: echo password_hash('Admin@1234', PASSWORD_DEFAULT);

-- ============================================================
-- Seed Data: Test citizen account
-- Password: Citizen@1234
-- ============================================================
INSERT INTO users (full_name, email, password, phone, role) VALUES
('Jean Baptiste Uwimana', 'citizen@cir.rw', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0781234567', 'citizen');

-- ============================================================
-- Seed Data: Sample issues for demonstration
-- ============================================================
INSERT INTO issues (user_id, category_id, title, description, latitude, longitude, location_name, severity, status, priority_score) VALUES
(2, 1, 'Large pothole on KN 5 Road', 'There is a dangerous pothole near the bus stop causing accidents and damaging vehicles.', -1.9441, 30.0619, 'Kigali, Nyarugenge', 'High', 'Pending', 3.85),
(2, 2, 'Severe flooding in Kimironko market', 'Heavy rain has caused flooding around Kimironko market blocking pedestrian access.', -1.9355, 30.1003, 'Kigali, Gasabo', 'Critical', 'In Progress', 4.72),
(2, 3, 'Street lights out on KK 15 Ave', 'Multiple street lights not working creating safety concerns at night.', -1.9580, 30.1127, 'Kigali, Kicukiro', 'Medium', 'Resolved', 2.60),
(2, 6, 'Sewage overflow near school', 'Sewage is overflowing near Ecole Primaire causing health hazard for children.', -1.9502, 30.0588, 'Kigali, Nyarugenge', 'Critical', 'Pending', 4.88);
 


-- CIR Migration: Profile pictures + Issue flagging
ALTER TABLE users
    MODIFY COLUMN profile_image VARCHAR(255) DEFAULT NULL;

ALTER TABLE issues
    ADD COLUMN IF NOT EXISTS flag_reason VARCHAR(500) DEFAULT NULL
        COMMENT 'Set by admin when issue needs citizen correction; cleared on re-submit',
    ADD COLUMN IF NOT EXISTS flagged_at TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS flagged_by INT DEFAULT NULL,
    ADD CONSTRAINT IF NOT EXISTS fk_issues_flagged_by
        FOREIGN KEY (flagged_by) REFERENCES users(id);

CREATE TABLE IF NOT EXISTS issue_edit_history (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    issue_id    INT NOT NULL,
    edited_by   INT NOT NULL,
    old_title       VARCHAR(200),
    old_description TEXT,
    old_severity    ENUM('Low','Medium','High','Critical'),
    old_image       VARCHAR(255),
    edit_note   VARCHAR(500) DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (issue_id)  REFERENCES issues(id) ON DELETE CASCADE,
    FOREIGN KEY (edited_by) REFERENCES users(id)
) ENGINE=InnoDB COMMENT='Immutable audit log of citizen edits';