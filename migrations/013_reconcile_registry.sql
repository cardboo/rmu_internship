-- =====================================================================
-- RMU Internship Tracker - v2 Migration 013
-- File: migrations/013_reconcile_registry.sql
--
-- Fixes the drift between `users` (role='student') and
-- `student_registry`. Two failure modes left rows out of sync:
--
--   A. admin/add_user.php created student users but never
--      mirrored them into student_registry. (Bug fixed in code
--      alongside this migration.)
--   B. Migration 005 backfilled the registry strictly: it only
--      inserted students whose department + programme strings
--      matched canonical rows. Anything else (NULL index_number,
--      programme typed in the dept field, etc.) was skipped.
--
-- This migration:
--   1. Flips is_claimed = 1 + claimed_user_id on any existing
--      registry row whose index_number matches a current student
--      user.
--   2. Inserts a registry row for any student user that has a
--      valid index + dept + programme but no registry entry.
--   3. Reports remaining unmappable student users (NULL index,
--      bad dept, bad programme) so admin can fix the data and
--      re-run.
--
-- Idempotent: UPDATE / INSERT IGNORE guards.
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1. Mark existing registry rows as claimed when a matching user
--    account exists.
-- ---------------------------------------------------------------------
UPDATE `student_registry` r
JOIN   `users` u
       ON u.index_number = r.index_number
      AND u.role = 'student'
SET    r.is_claimed      = 1,
       r.claimed_user_id = u.id
WHERE  r.is_claimed = 0
   OR  r.claimed_user_id IS NULL;

-- ---------------------------------------------------------------------
-- 2. Insert registry rows for student users that aren't there yet
--    but whose dept + programme map cleanly.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `student_registry`
    (`index_number`, `full_name`, `email`, `department_id`, `program_id`,
     `level`, `gender`, `is_claimed`, `claimed_user_id`)
SELECT u.index_number, u.full_name, u.email, d.id, p.id,
       u.level, u.gender, 1, u.id
FROM   `users` u
JOIN   `departments` d ON d.name = u.department
JOIN   `programs`    p ON p.name = u.program AND p.department_id = d.id
LEFT JOIN `student_registry` r ON r.index_number = u.index_number
WHERE  u.role         = 'student'
  AND  u.index_number IS NOT NULL
  AND  u.index_number <> ''
  AND  COALESCE(u.is_archived, 0) = 0
  AND  r.index_number IS NULL;

-- ---------------------------------------------------------------------
-- 3. Sanity report — students that still won't appear in the registry,
--    grouped by reason. Admin should fix these and re-run this migration
--    (it's idempotent).
-- ---------------------------------------------------------------------
SELECT
    u.id,
    u.full_name,
    u.email,
    u.index_number,
    u.department  AS user_department,
    u.program     AS user_program,
    CASE
        WHEN u.index_number IS NULL OR u.index_number = '' THEN 'no index_number'
        WHEN d.id IS NULL THEN CONCAT('unknown department: ', COALESCE(u.department, ''))
        WHEN p.id IS NULL THEN 'unknown program in this department'
        ELSE 'OK'
    END AS reason
FROM       `users` u
LEFT JOIN  `departments`      d ON d.name = u.department
LEFT JOIN  `programs`         p ON p.name = u.program AND p.department_id = d.id
LEFT JOIN  `student_registry` r ON r.index_number = u.index_number
WHERE  u.role = 'student'
  AND  COALESCE(u.is_archived, 0) = 0
  AND  (
         u.index_number IS NULL
      OR u.index_number = ''
      OR r.index_number IS NULL
       );

COMMIT;
