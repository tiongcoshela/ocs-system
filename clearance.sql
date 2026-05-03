-- =============================================
--  Asian College Online Clearance System
--  FRESH DATABASE SETUP
--  All passwords = password123
-- =============================================

CREATE DATABASE IF NOT EXISTS clearance_db;
USE clearance_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS clearance_items;
DROP TABLE IF EXISTS clearance_requests;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ─── USERS TABLE ───────────────────────────
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(100) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('student','admin','library','toolroom','cashier','mis','tvet_coordinator','tvet_director') NOT NULL,
    student_id  VARCHAR(20) DEFAULT NULL,
    year_level  TINYINT DEFAULT NULL COMMENT '1, 2, or 3 — only for students',
    course      ENUM('DIT','DHT') NOT NULL DEFAULT 'DIT',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── CLEARANCE REQUESTS TABLE ──────────────
CREATE TABLE clearance_requests (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT NOT NULL,
    status      ENUM('pending','approved','rejected') DEFAULT 'pending',
    semester    VARCHAR(20) DEFAULT '2nd Semester',
    school_year VARCHAR(20) DEFAULT '2025-2026',
    course      VARCHAR(100) DEFAULT 'Diploma in Information Technology',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─── CLEARANCE ITEMS TABLE ─────────────────
CREATE TABLE clearance_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    request_id  INT NOT NULL,
    office      ENUM('library','toolroom','cashier','mis','tvet_coordinator','tvet_director') NOT NULL,
    status      ENUM('pending','approved','rejected','not_started') DEFAULT 'not_started',
    remarks     VARCHAR(255) DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (request_id) REFERENCES clearance_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ─── NOTIFICATIONS TABLE ───────────────────
CREATE TABLE notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    message    VARCHAR(255) NOT NULL,
    type       ENUM('success','warning','error','info') DEFAULT 'info',
    is_read    TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =============================================
--  SAMPLE ACCOUNTS  (Run fix_passwords.php after!)
-- =============================================

INSERT INTO users (name, email, password, role, student_id, year_level, course) VALUES
-- Students
('Nina Mananquil',           'nina@asian.edu.ph',         'TEMP', 'student',          '2024-DIP-001', 3, 'DIT'),
('Shela Tiongco',            'shela@asian.edu.ph',        'TEMP', 'student',          '2024-DIP-002', 2, 'DIT'),
('Marc Mapili',              'marc@asian.edu.ph',         'TEMP', 'student',          '2024-DIP-003', 1, 'DIT'),
('Ana Abella',               'ana@asian.edu.ph',          'TEMP', 'student',          '2024-DIP-004', 1, 'DHT'),
('Venus Fabrigras',          'venus@asian.edu.ph',        'TEMP', 'student',          '2024-DIP-005', 2, 'DHT'),
('Derek Baisac',             'derek@asian.edu.ph',        'TEMP', 'student',          '2024-DIP-006', 3, 'DHT'),
-- Admin
('Admin User',               'admin@asian.edu.ph',        'TEMP', 'admin',            NULL, NULL, 'DIT'),
-- Office Staff
('Mr. Ramel Nudo',           'library@asian.edu.ph',      'TEMP', 'library',          NULL, NULL, 'DIT'),
('Mr. Limuel Panday',        'toolroom@asian.edu.ph',     'TEMP', 'toolroom',         NULL, NULL, 'DIT'),
('Ms. Glee Mae Soriano',     'cashier@asian.edu.ph',      'TEMP', 'cashier',          NULL, NULL, 'DIT'),
('Mr. Christian B. Solis',   'mis@asian.edu.ph',          'TEMP', 'mis',              NULL, NULL, 'DIT'),
('Ms. Reyna F. Villadares',  'coordinator@asian.edu.ph',  'TEMP', 'tvet_coordinator', NULL, NULL, 'DIT'),
('Ms. Melody C. Prado',      'director@asian.edu.ph',     'TEMP', 'tvet_director',    NULL, NULL, 'DIT');

-- ─── NINA (3rd year — HAS Cashier) ─────────
INSERT INTO clearance_requests (student_id, status, semester, school_year, course)
VALUES (1, 'pending', '2nd Semester', '2025-2026', 'Diploma in Information Technology');

INSERT INTO clearance_items (request_id, office, status, remarks) VALUES
(1, 'library',          'approved',     'No overdue books on record'),
(1, 'toolroom',         'approved',     'No borrowed tools/equipment'),
(1, 'cashier',          'pending',      'Graduation fee payment pending'),
(1, 'mis',              'pending',      'Pending system account verification'),
(1, 'tvet_coordinator', 'rejected',     'Missing final project submission'),
(1, 'tvet_director',    'not_started',  NULL);

-- ─── SHELA (2nd year — NO Cashier) ─────────
INSERT INTO clearance_requests (student_id, status, semester, school_year, course)
VALUES (2, 'pending', '2nd Semester', '2025-2026', 'Diploma in Information Technology');

INSERT INTO clearance_items (request_id, office, status, remarks) VALUES
(2, 'library',          'pending',      NULL),
(2, 'toolroom',         'not_started',  NULL),
(2, 'mis',              'not_started',  NULL),
(2, 'tvet_coordinator', 'not_started',  NULL),
(2, 'tvet_director',    'not_started',  NULL);

-- ─── MARC (1st year — NO Cashier) ──────────
INSERT INTO clearance_requests (student_id, status, semester, school_year, course)
VALUES (3, 'pending', '2nd Semester', '2025-2026', 'Diploma in Information Technology');

INSERT INTO clearance_items (request_id, office, status, remarks) VALUES
(3, 'library',          'not_started',  NULL),
(3, 'toolroom',         'not_started',  NULL),
(3, 'mis',              'not_started',  NULL),
(3, 'tvet_coordinator', 'not_started',  NULL),
(3, 'tvet_director',    'not_started',  NULL);

-- ─── NOTIFICATIONS ──────────────────────────
INSERT INTO notifications (user_id, message, type, is_read) VALUES
(1, 'Library approved your clearance request.',                                    'success', 0),
(1, 'Tool Room approved your clearance. No borrowed tools.',                       'success', 1),
(1, 'TVET Coordinator rejected your request. Reason: Missing final project.',      'error',   0),
(1, 'MIS is reviewing your clearance.',                                            'warning', 0);
