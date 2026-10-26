-- (Aiven/cloud version: no CREATE DATABASE / USE. Import into your existing database, e.g. defaultdb.)
-- SafeSpeak Database Schema (clean import)
-- Import this file in phpMyAdmin or run with MySQL CLI.


-- ADMIN TABLE
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    institution VARCHAR(255) DEFAULT 'University Proctor Office',
    role ENUM('super_admin', 'proctor', 'moderator') DEFAULT 'proctor',
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- STUDENTS TABLE
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    department VARCHAR(255) DEFAULT NULL,
    batch VARCHAR(50) DEFAULT NULL,
    institution VARCHAR(255) DEFAULT 'University',
    phone VARCHAR(20) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    is_verified TINYINT(1) DEFAULT 0,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- STUDENT DIRECTORY TABLE
CREATE TABLE IF NOT EXISTS student_directory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(255) NOT NULL,
    age INT DEFAULT NULL,
    email VARCHAR(255) NOT NULL,
    department VARCHAR(100) DEFAULT NULL,
    gpa DECIMAL(3,2) DEFAULT NULL,
    graduation_year INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student_id (student_id),
    INDEX idx_name (full_name),
    INDEX idx_department (department)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- REPORTS TABLE
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tracking_id VARCHAR(20) NOT NULL UNIQUE,
    student_id INT NULL,
    category ENUM('bullying', 'harassment', 'sexual_harassment', 'academic_misconduct', 'discrimination', 'violence', 'mental_health', 'other') NOT NULL,
    title VARCHAR(500) NOT NULL,
    description TEXT NOT NULL,
    incident_date DATE NULL,
    incident_location VARCHAR(255) NULL,
    accused_student_id VARCHAR(50) DEFAULT NULL,
    accused_name VARCHAR(255) DEFAULT NULL,
    accused_department VARCHAR(100) DEFAULT NULL,
    involved_persons TEXT NULL,
    is_anonymous TINYINT(1) DEFAULT 1,
    evidence_files TEXT NULL,
    notify_email VARCHAR(255) DEFAULT NULL,
    notify_opt_in TINYINT(1) DEFAULT 0,
    status ENUM('pending', 'under_review', 'investigating', 'resolved', 'rejected', 'escalated') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    ai_sentiment_score DECIMAL(5,4) DEFAULT NULL,
    ai_sentiment_label ENUM('positive', 'neutral', 'negative', 'very_negative') DEFAULT NULL,
    ai_priority_score DECIMAL(5,4) DEFAULT NULL,
    ai_spam_score DECIMAL(5,4) DEFAULT NULL,
    ai_is_spam TINYINT(1) DEFAULT 0,
    ai_risk_keywords TEXT DEFAULT NULL,
    ai_analysis_done TINYINT(1) DEFAULT 0,
    ai_notes TEXT DEFAULT NULL,
    content_hash CHAR(32) DEFAULT NULL,
    spam_score DECIMAL(5,4) DEFAULT NULL,
    spam_flags TEXT DEFAULT NULL,
    spam_needs_verification TINYINT(1) DEFAULT 0,
    manual_spam TINYINT(1) DEFAULT 0,
    manual_spam_by INT NULL,
    manual_spam_at DATETIME NULL,
    manual_spam_note TEXT NULL,
    id_verification_required TINYINT(1) DEFAULT 0,
    id_verification_status ENUM('not_requested','requested','submitted','approved','rejected') DEFAULT 'not_requested',
    id_verification_file VARCHAR(255) DEFAULT NULL,
    id_verification_submitted_at DATETIME NULL,
    id_verification_requested_by INT NULL,
    id_verification_requested_at DATETIME NULL,
    id_verification_reviewed_by INT NULL,
    id_verification_reviewed_at DATETIME NULL,
    id_verification_review_note TEXT NULL,
    assigned_to INT NULL,
    admin_notes TEXT NULL,
    accused_verified TINYINT(1) DEFAULT 0,
    accused_verified_by INT NULL,
    accused_verified_at DATETIME NULL,
    resolved_at DATETIME NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES admins(id) ON DELETE SET NULL,
    FOREIGN KEY (accused_verified_by) REFERENCES admins(id) ON DELETE SET NULL,
    FOREIGN KEY (manual_spam_by) REFERENCES admins(id) ON DELETE SET NULL,
    FOREIGN KEY (id_verification_requested_by) REFERENCES admins(id) ON DELETE SET NULL,
    FOREIGN KEY (id_verification_reviewed_by) REFERENCES admins(id) ON DELETE SET NULL
);

-- SPAM SETTINGS
CREATE TABLE IF NOT EXISTS spam_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blacklist_keywords TEXT NULL,
    disposable_domains TEXT NULL,
    rate_limit_max INT DEFAULT 5,
    hard_rate_limit INT DEFAULT 7,
    duplicate_window_days INT DEFAULT 7,
    duplicate_threshold INT DEFAULT 2,
    verification_threshold DECIMAL(5,4) DEFAULT 0.6,
    spam_score_threshold DECIMAL(5,4) DEFAULT 0.7,
    link_short_wordcount INT DEFAULT 25,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES admins(id) ON DELETE SET NULL
);

-- REPORT STATUS HISTORY
CREATE TABLE IF NOT EXISTS report_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    old_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NOT NULL,
    changed_by INT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES admins(id) ON DELETE SET NULL
);

-- REPORT NOTES
CREATE TABLE IF NOT EXISTS report_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    admin_id INT NOT NULL,
    note TEXT NOT NULL,
    is_internal TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
);

-- REPORT MESSAGES (ANONYMOUS TWO-WAY)
CREATE TABLE IF NOT EXISTS report_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    sender_type ENUM('admin','reporter') NOT NULL,
    admin_id INT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

-- EVIDENCE ACCESS LOG
CREATE TABLE IF NOT EXISTS report_evidence_access_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    admin_id INT NULL,
    file_name VARCHAR(255) NOT NULL,
    access_type ENUM('view','download','link') DEFAULT 'view',
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

-- EVIDENCE ANNOTATIONS
CREATE TABLE IF NOT EXISTS report_evidence_annotations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    admin_id INT NULL,
    evidence_key TEXT NOT NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
);

-- OUTCOME SURVEYS
CREATE TABLE IF NOT EXISTS report_outcome_surveys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    satisfaction_score TINYINT NOT NULL,
    response_time_score TINYINT NOT NULL,
    fairness_score TINYINT NOT NULL,
    comments TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
);

-- FOLLOW-UP TOKENS (ANONYMOUS)
CREATE TABLE IF NOT EXISTS report_followup_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    expires_at DATETIME NULL,
    UNIQUE KEY uniq_report_token (report_id),
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
);

-- REPORT TASKS
CREATE TABLE IF NOT EXISTS report_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    status ENUM('open','done') DEFAULT 'open',
    due_date DATE NULL,
    created_by INT NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
);

-- UNIVERSITY TERMS (PDF UPLOADS)
CREATE TABLE IF NOT EXISTS university_terms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_name VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    file_name VARCHAR(255) NOT NULL,
    uploaded_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES admins(id) ON DELETE SET NULL
);

-- SYSTEM LOGS
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(255) NOT NULL,
    user_type ENUM('admin', 'student', 'anonymous') DEFAULT 'anonymous',
    user_id INT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- SIMPLE RATE LIMIT TABLE
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    action VARCHAR(100) NOT NULL,
    count INT DEFAULT 1,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_action (ip_address, action)
);

-- INDEXES
CREATE INDEX idx_reports_status ON reports(status);
CREATE INDEX idx_reports_priority ON reports(priority);
CREATE INDEX idx_reports_category ON reports(category);
CREATE INDEX idx_reports_tracking ON reports(tracking_id);
CREATE INDEX idx_reports_content_hash ON reports(content_hash);
CREATE INDEX idx_accused_student ON reports(accused_student_id);
CREATE INDEX idx_reports_created ON reports(created_at);
CREATE INDEX idx_students_email ON students(email);
CREATE INDEX idx_students_sid ON students(student_id);

-- DEFAULT ADMINS
-- Password for both: password
INSERT INTO admins (username, email, password, full_name, institution, role, is_active)
SELECT 'admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'SafeSpeak BD', 'super_admin', 1
WHERE NOT EXISTS (SELECT 1 FROM admins WHERE username = 'admin');

INSERT INTO admins (username, email, password, full_name, institution, role, is_active)
SELECT 'proctor', 'proctor@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Proctor Officer', 'University Proctor Office', 'proctor', 1
WHERE NOT EXISTS (SELECT 1 FROM admins WHERE username = 'proctor');

-- OPTIONAL TEST STUDENT
-- Password: password
INSERT INTO students (student_id, full_name, email, password, department, batch, institution, is_active, is_verified)
SELECT '011221157', 'Demo Student', 'apon@student.university.bd', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CSE', '2022', 'Demo University', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM students WHERE email = 'apon@student.university.bd');
