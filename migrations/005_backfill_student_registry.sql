-- =====================================================================
-- RMU Internship Tracker - v2 Migration 005
-- File: migrations/005_backfill_student_registry.sql
--
-- The student_registry table was added in migration 001 to act as the
-- authoritative roster of admitted students (the source secretaries
-- pull from when registering accounts). It starts empty.
--
-- This migration backfills it from the EXISTING `users` rows that
-- already have role='student' and a non-empty index_number, marking
-- them as already claimed (claimed_user_id = users.id) so the admin
-- can see all current students in admin/registry.php immediately.
--
-- Best-effort matching:
--   * Departments are matched by name (users.department -> departments.name)
--   * Programs are matched by name within that department
--     (users.program -> programs.name AND department_id matches)
--   * Rows whose dept/program don't match the canonical taxonomy are
--     SKIPPED and reported in the final SELECT — admin can fix the
--     user record (or add the program in admin/programs.php) and
--     re-run this migration safely.
--
-- Idempotent: uses INSERT IGNORE (PRIMARY KEY is index_number).
-- =====================================================================

START TRANSACTION;

INSERT IGNORE INTO `student_registry` (
    `index_number`,
    `full_name`,
    `department_id`,
    `program_id`,
    `level`,
    `gender`,
    `is_claimed`,
    `claimed_user_id`
)
SELECT
    u.index_number,
    u.full_name,
    d.id AS department_id,
    p.id AS program_id,
    u.level,
    u.gender,
    1 AS is_claimed,
    u.id AS claimed_user_id
FROM `users` u
JOIN `departments` d ON d.name = u.department
JOIN `programs`    p ON p.name = u.program AND p.department_id = d.id
WHERE u.role         = 'student'
  AND u.index_number IS NOT NULL
  AND u.index_number <> '';

-- Sanity report: students that were skipped because their dept/program
-- string doesn't map to a row in departments/programs. Admin can use
-- this list to clean up the data, then re-run this migration.
SELECT
    u.id,
    u.full_name,
    u.email,
    u.index_number,
    u.department AS user_department,
    u.program    AS user_program,
    CASE
        WHEN u.index_number IS NULL OR u.index_number = '' THEN 'no index_number'
        WHEN d.id IS NULL  THEN CONCAT('unknown department: ', u.department)
        WHEN p.id IS NULL  THEN 'unknown program in this department'
        ELSE 'OK'
    END AS reason
FROM `users` u
LEFT JOIN `departments` d ON d.name = u.department
LEFT JOIN `programs`    p ON p.name = u.program AND p.department_id = d.id
WHERE u.role = 'student'
  AND (
        u.index_number IS NULL OR u.index_number = ''
     OR d.id IS NULL
     OR p.id IS NULL
  );

COMMIT;
