-- =============================================
-- UPDATE EXISTING clearance_db (SIL roles removed)
-- Run this in phpMyAdmin SQL tab
-- =============================================

USE clearance_db;

-- STEP 1: Ensure student profile fields exist
ALTER TABLE users
ADD COLUMN IF NOT EXISTS year_level TINYINT DEFAULT NULL COMMENT '1, 2, or 3 - only for students'
AFTER student_id;

ALTER TABLE users
ADD COLUMN IF NOT EXISTS course ENUM('DIT','DHT') NOT NULL DEFAULT 'DIT'
AFTER year_level;

-- STEP 2: Normalize legacy role/office labels
UPDATE users SET role = 'mis' WHERE role = 'tsg';
UPDATE clearance_items SET office = 'mis' WHERE office = 'tsg';

-- STEP 3: Remove SIL roles/data before enum shrink
DELETE FROM clearance_items WHERE office IN ('sil_coordinator_dit', 'sil_coordinator_dht', 'sil_dit', 'sil_dht');
DELETE FROM users WHERE role IN ('sil_coordinator_dit', 'sil_coordinator_dht', 'sil_dit', 'sil_dht');
DELETE FROM users WHERE email IN ('sil.dit@asian.edu.ph', 'sil.dht@asian.edu.ph', 'maeann@asian.edu.ph', 'roland@asian.edu.ph');

-- STEP 4: Keep only supported roles/offices
ALTER TABLE users
MODIFY COLUMN role ENUM(
    'student','admin','library','toolroom','cashier','mis','tvet_coordinator','tvet_director'
) NOT NULL;

ALTER TABLE clearance_items
MODIFY COLUMN office ENUM(
    'library','toolroom','cashier','mis','tvet_coordinator','tvet_director'
) NOT NULL;

-- STEP 5: Update office account display names
UPDATE users SET name = 'Mr. Limuel Panday'      WHERE email = 'toolroom@asian.edu.ph';
UPDATE users SET name = 'Ms. Glee Mae Soriano'   WHERE email = 'cashier@asian.edu.ph';
UPDATE users SET name = 'Mr. Christian B. Solis' WHERE email = 'mis@asian.edu.ph';
UPDATE users SET name = 'Ms. Reyna F. Villadares' WHERE email = 'coordinator@asian.edu.ph';
UPDATE users SET name = 'Ms. Melody C. Prado'    WHERE email = 'director@asian.edu.ph';

-- STEP 6: Ensure route-specific offices exist per student request
INSERT INTO clearance_items (request_id, office, status, remarks)
SELECT cr.id, 'mis', 'not_started', NULL
FROM clearance_requests cr
JOIN users u ON u.id = cr.student_id
WHERE u.course = 'DIT'
AND NOT EXISTS (
    SELECT 1 FROM clearance_items ci WHERE ci.request_id = cr.id AND ci.office = 'mis'
);

INSERT INTO clearance_items (request_id, office, status, remarks)
SELECT cr.id, 'toolroom', 'not_started', NULL
FROM clearance_requests cr
JOIN users u ON u.id = cr.student_id
WHERE u.course = 'DHT'
AND NOT EXISTS (
    SELECT 1 FROM clearance_items ci WHERE ci.request_id = cr.id AND ci.office = 'toolroom'
);

INSERT INTO clearance_items (request_id, office, status, remarks)
SELECT cr.id, 'cashier', 'not_started', NULL
FROM clearance_requests cr
JOIN users u ON u.id = cr.student_id
WHERE u.year_level = 3
AND NOT EXISTS (
    SELECT 1 FROM clearance_items ci WHERE ci.request_id = cr.id AND ci.office = 'cashier'
);

INSERT INTO clearance_items (request_id, office, status, remarks)
SELECT cr.id, 'tvet_coordinator', 'not_started', NULL
FROM clearance_requests cr
WHERE NOT EXISTS (
    SELECT 1 FROM clearance_items ci WHERE ci.request_id = cr.id AND ci.office = 'tvet_coordinator'
);

INSERT INTO clearance_items (request_id, office, status, remarks)
SELECT cr.id, 'tvet_director', 'not_started', NULL
FROM clearance_requests cr
WHERE NOT EXISTS (
    SELECT 1 FROM clearance_items ci WHERE ci.request_id = cr.id AND ci.office = 'tvet_director'
);

-- VERIFY
SELECT 'USERS' AS check_table, role, name, email FROM users ORDER BY role, name;
SELECT 'CLEARANCE ITEMS' AS check_table, ci.office, ci.status, u.name AS student
FROM clearance_items ci
JOIN clearance_requests cr ON cr.id = ci.request_id
JOIN users u ON u.id = cr.student_id
ORDER BY u.name, ci.office;
