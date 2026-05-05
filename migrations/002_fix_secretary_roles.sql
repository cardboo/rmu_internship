-- =====================================================================
-- RMU Internship Tracker - v2 Migration 002
-- File: migrations/002_fix_secretary_roles.sql
--
-- During seeding, several secretary accounts ended up with an empty
-- string in the `role` column (rows 19, 26, 33, 40, 47, 54 in the
-- original dump), which prevented them from logging in.
--
-- This migration:
--   1. Backfills role='secretary' for accounts that look like
--      department secretaries (email starts with 'sec.' or job_title
--      contains 'Department Secretary') AND have an empty role.
--   2. Optionally tightens the column to NOT NULL with a sensible
--      default — left commented out because some test rows may
--      legitimately have NULL/'' in dev environments.
--
-- Idempotent: safe to run more than once.
-- =====================================================================

START TRANSACTION;

UPDATE `users`
SET `role` = 'secretary'
WHERE (`role` IS NULL OR `role` = '')
  AND (
        `email`     LIKE 'sec.%'
     OR `job_title` LIKE '%Department Secretary%'
  );

-- Sanity check: report how many rows still have an empty role.
-- (Phpmyadmin will print this as a select after the update.)
SELECT COUNT(*) AS unfixed_empty_role_rows
FROM `users`
WHERE `role` IS NULL OR `role` = '';

COMMIT;

-- Optional hardening (uncomment after confirming all rows have a role):
-- ALTER TABLE `users`
--   MODIFY `role` ENUM('admin','hod','secretary','student','registry')
--                 NOT NULL DEFAULT 'student';
