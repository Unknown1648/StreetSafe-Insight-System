-- StreetSafe Insight System Database Schema
-- Empty schema: no seeded users, locations, or incidents.
-- Run this against the existing streetsafe_insight database.

CREATE TABLE IF NOT EXISTS accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role ENUM('community', 'police') NOT NULL,
    full_name VARCHAR(255) DEFAULT NULL,
    display_name VARCHAR(255) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    badge_number VARCHAR(50) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    neighborhood VARCHAR(255) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    personal_details_public BOOLEAN DEFAULT FALSE,
    profile_note TEXT DEFAULT NULL,
    station VARCHAR(255) DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email (email),
    UNIQUE KEY uq_phone (phone),
    UNIQUE KEY uq_badge (badge_number),
    INDEX idx_role (role)
);

CREATE TABLE IF NOT EXISTS streets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    area VARCHAR(255) NOT NULL,
    lat DECIMAL(10, 7) DEFAULT NULL,
    lng DECIMAL(10, 7) DEFAULT NULL,
    lighting ENUM('good', 'moderate', 'poor') DEFAULT 'moderate',
    cctv BOOLEAN DEFAULT FALSE,
    neighborhood_watch BOOLEAN DEFAULT FALSE,
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES accounts(id) ON DELETE SET NULL,
    INDEX idx_area (area),
    INDEX idx_location (lat, lng)
);

CREATE TABLE IF NOT EXISTS incidents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    street_id INT DEFAULT NULL,
    reporter_id INT DEFAULT NULL,
    reporter_role ENUM('community', 'police', 'anonymous') DEFAULT 'community',
    source_name VARCHAR(255) DEFAULT NULL,
    is_anonymous BOOLEAN DEFAULT FALSE,
    type VARCHAR(100) NOT NULL,
    severity ENUM('low', 'medium', 'high') DEFAULT 'medium',
    description TEXT,
    status ENUM('reported', 'received', 'in_progress', 'follow_up', 'resolved') DEFAULT 'reported',
    response_time INT DEFAULT 0,
    response_message TEXT DEFAULT NULL,
    police_action VARCHAR(100) DEFAULT NULL,
    responded_by INT DEFAULT NULL,
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (street_id) REFERENCES streets(id) ON DELETE SET NULL,
    FOREIGN KEY (reporter_id) REFERENCES accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (responded_by) REFERENCES accounts(id) ON DELETE SET NULL,
    INDEX idx_street (street_id),
    INDEX idx_reporter (reporter_id),
    INDEX idx_status (status),
    INDEX idx_reported_at (reported_at),
    INDEX idx_severity (severity)
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_id INT NOT NULL,
    sender_id INT DEFAULT NULL,
    incident_id INT DEFAULT NULL,
    category ENUM('response', 'alert', 'status_update') DEFAULT 'response',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP DEFAULT NULL,
    FOREIGN KEY (recipient_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
    INDEX idx_recipient (recipient_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
);

CREATE TABLE IF NOT EXISTS report_audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    incident_id INT DEFAULT NULL,
    action_type VARCHAR(100) NOT NULL,
    actor_id INT DEFAULT NULL,
    details TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE SET NULL,
    FOREIGN KEY (actor_id) REFERENCES accounts(id) ON DELETE SET NULL,
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at)
);
