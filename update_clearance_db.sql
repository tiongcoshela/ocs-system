-- =============================================
-- UPDATE EXISTING clearance_db
-- Run this in phpMyAdmin SQL tab
-- This will NOT delete your existing data
-- =============================================

USE clearance_db;

-- STEP 1: Add year_level column to users
ALTER TABLE users
ADD COLUMN IF NOT EXISTS year_level TINYINT DEFAULT NULL COMMENT '1, 2, or 3 - only for students'
AFTER student_id;

-- STEP 1B: Add course column to users
ALTER TABLE users
ADD COLUMN IF NOT EXISTS course ENUM('DIT','DHT') NOT NULL DEFAULT 'DIT'
AFTER year_level;

-- STEP 2: Update existing tsg user role to mis
UPDATE users SET role = 'mis' WHERE role = 'tsg';

-- STEP 3: Update users ENUM to include cashier and mis, remove tsg
ALTER TABLE users
MODIFY COLUMN role ENUM(
    'student','admin','library','toolroom','cashier','mis','tvet_coordinator','tvet_director'
) NOT NULL;

-- STEP 4: Update clearance_items ENUM (rename tsg to mis)
-- First update existing tsg rows to mis
UPDATE clearance_items SET office = 'mis' WHERE office = 'tsg';

ALTER TABLE clearance_items
MODIFY COLUMN office ENUM(
    'library','toolroom','cashier','mis','tvet_coordinator','tvet_director'
) NOT NULL;

-- STEP 5: Update in-charge names to match new form
UPDATE users SET name = 'Mr. Limuel Panday'      WHERE email = 'toolroom@asian.edu.ph';
UPDATE users SET name = 'Ms. Glee Mae Soriano'   WHERE email = 'cashier@asian.edu.ph';
UPDATE users SET name = 'Mr. Christian B. Solis' WHERE email = 'mis@asian.edu.ph';
UPDATE users SET name = 'Ms. Reyna F. Villadares' WHERE email = 'coordinator@asian.edu.ph';
UPDATE users SET name = 'Ms. Melody C. Prado'    WHERE email = 'director@asian.edu.ph';

-- STEP 6: Set year levels for existing students
UPDATE users SET year_level = 3 WHERE email = 'nina@asian.edu.ph';
UPDATE users SET year_level = 2 WHERE email = 'shela@asian.edu.ph';
UPDATE users SET year_level = 1 WHERE email = 'marc@asian.edu.ph';
UPDATE users SET course = 'DIT' WHERE email IN ('nina@asian.edu.ph', 'shela@asian.edu.ph', 'marc@asian.edu.ph');

-- STEP 6B: Add requested student accounts if not exists
-- Temporary login password: ChangeMe123!
INSERT INTO users (name, email, password, role, student_id, year_level, course)
SELECT 'Ana Abella', 'ana@asian.edu.ph', '$2y$10$SkvNZ3c88I8iZFAPnOXOtus4uJ6xKtNvg4TzSNcD700UPYsNs20Hi', 'student', '2024-DIP-004', 1, 'DHT'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'ana@asian.edu.ph');

INSERT INTO users (name, email, password, role, student_id, year_level, course)
SELECT 'Venus Fabrigras', 'venus@asian.edu.ph', '$2y$10$SkvNZ3c88I8iZFAPnOXOtus4uJ6xKtNvg4TzSNcD700UPYsNs20Hi', 'student', '2024-DIP-005', 2, 'DHT'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'venus@asian.edu.ph');

INSERT INTO users (name, email, password, role, student_id, year_level, course)
SELECT 'Derek Baisac', 'derek@asian.edu.ph', '$2y$10$SkvNZ3c88I8iZFAPnOXOtus4uJ6xKtNvg4TzSNcD700UPYsNs20Hi', 'student', '2024-DIP-006', 3, 'DHT'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'derek@asian.edu.ph');

-- STEP 6C: Ensure DHT students have users.course = DHT
UPDATE users SET course = 'DHT' WHERE email IN ('ana@asian.edu.ph', 'venus@asian.edu.ph', 'derek@asian.edu.ph');

-- STEP 7: Add mis account if not exists
-- Temporary login password is ChangeMe123! (bcrypt hash below). Change it after first login.
INSERT INTO users (name, email, password, role, student_id, year_level)
SELECT 'Mr. Christian B. Solis', 'mis@asian.edu.ph', '$2y$10$SkvNZ3c88I8iZFAPnOXOtus4uJ6xKtNvg4TzSNcD700UPYsNs20Hi', 'mis', NULL, NULL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'mis@asian.edu.ph');

-- STEP 8: Add cashier account if not exists
INSERT INTO users (name, email, password, role, student_id, year_level)
SELECT 'Ms. Glee Mae Soriano', 'cashier@asian.edu.ph', '$2y$10$SkvNZ3c88I8iZFAPnOXOtus4uJ6xKtNvg4TzSNcD700UPYsNs20Hi', 'cashier', NULL, NULL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'cashier@asian.edu.ph');

-- STEP 9: Add MIS clearance items only for DIT requests that do not have it
INSERT INTO clearance_items (request_id, office, status, remarks)
SELECT cr.id, 'mis', 'not_started', NULL
FROM clearance_requests cr
JOIN users u ON u.id = cr.student_id
WHERE u.course = 'DIT'
AND NOT EXISTS (
    SELECT 1 FROM clearance_items ci WHERE ci.request_id = cr.id AND ci.office = 'mis'
);

-- STEP 9B: Add Tool Room clearance items only for DHT requests that do not have it
INSERT INTO clearance_items (request_id, office, status, remarks)
SELECT cr.id, 'toolroom', 'not_started', NULL
FROM clearance_requests cr
JOIN users u ON u.id = cr.student_id
WHERE u.course = 'DHT'
AND NOT EXISTS (
    SELECT 1 FROM clearance_items ci WHERE ci.request_id = cr.id AND ci.office = 'toolroom'
);

-- STEP 9C: Convert wrong DHT MIS items to Tool Room if not yet approved
UPDATE clearance_items ci
JOIN clearance_requests cr ON cr.id = ci.request_id
JOIN users u ON u.id = cr.student_id
SET ci.office = 'toolroom'
WHERE u.course = 'DHT'
AND ci.office = 'mis'
AND ci.status <> 'approved'
AND NOT EXISTS (
    SELECT 1 FROM clearance_items ci2 WHERE ci2.request_id = ci.request_id AND ci2.office = 'toolroom'
);

-- STEP 9D: Remove wrong non-approved DHT MIS items when Tool Room already exists
DELETE ci
FROM clearance_items ci
JOIN clearance_requests cr ON cr.id = ci.request_id
JOIN users u ON u.id = cr.student_id
WHERE u.course = 'DHT'
AND ci.office = 'mis'
AND ci.status <> 'approved'
AND EXISTS (
    SELECT 1 FROM clearance_items ci2 WHERE ci2.request_id = ci.request_id AND ci2.office = 'toolroom'
);

-- STEP 10: Add cashier clearance items ONLY for 3rd year students
INSERT INTO clearance_items (request_id, office, status, remarks)
SELECT cr.id, 'cashier', 'not_started', NULL
FROM clearance_requests cr
JOIN users u ON u.id = cr.student_id
WHERE u.year_level = 3
AND NOT EXISTS (
    SELECT 1 FROM clearance_items ci WHERE ci.request_id = cr.id AND ci.office = 'cashier'
);

-- VERIFY: Check results
SELECT 'USERS' AS check_table, role, name, email FROM users ORDER BY role;
SELECT 'CLEARANCE ITEMS' AS check_table, ci.office, ci.status, u.name AS student
FROM clearance_items ci
JOIN clearance_requests cr ON cr.id = ci.request_id
JOIN users u ON u.id = cr.student_id
ORDER BY u.name, ci.office;
