-- =====================================================================
-- RMU Internship Tracker - v2 Migration 014
-- File: migrations/014_backfill_registry_fields.sql
--
-- Migration 013's INSERT IGNORE only created NEW registry rows; it
-- left existing ones alone. So registry rows that pre-date migration
-- 006 (when the email column was added) still have email = NULL even
-- though the matching user has it. Same risk for full_name / level /
-- gender if those changed on the user record after the registry row
-- was first created.
--
-- This migration refreshes the editable fields on every registry row
-- from the linked user account. It does NOT touch:
--   * index_number      (the PK / link key — never overwrite)
--   * department_id     (admin-managed taxonomy)
--   * program_id        (admin-managed taxonomy)
--   * is_claimed / claimed_user_id (managed by migration 013)
--
-- Idempotent: only writes when the source has a value AND the target
-- is null or empty (so we don't clobber registry-office data with a
-- blank user value).
-- =====================================================================

START TRANSACTION;

-- 1. Email
UPDATE `student_registry` r
JOIN   `users` u
       ON u.index_number = r.index_number
      AND u.role = 'student'
SET    r.email = u.email
WHERE  (r.email IS NULL OR r.email = '')
  AND  u.email IS NOT NULL
  AND  u.email <> '';

-- 2. Full name (registry was originally seeded with the index-office
--    spelling; if it's blank or matches the placeholder, refresh from
--    the user record)
UPDATE `student_registry` r
JOIN   `users` u
       ON u.index_number = r.index_number
      AND u.role = 'student'
SET    r.full_name = u.full_name
WHERE  (r.full_name IS NULL OR r.full_name = '')
  AND  u.full_name IS NOT NULL
  AND  u.full_name <> '';

-- 3. Level
UPDATE `student_registry` r
JOIN   `users` u
       ON u.index_number = r.index_number
      AND u.role = 'student'
SET    r.level = u.level
WHERE  (r.level IS NULL OR r.level = '')
  AND  u.level IS NOT NULL
  AND  u.level <> '';

-- 4. Gender
UPDATE `student_registry` r
JOIN   `users` u
       ON u.index_number = r.index_number
      AND u.role = 'student'
SET    r.gender = u.gender
WHERE  (r.gender IS NULL OR r.gender = '')
  AND  u.gender IS NOT NULL;

-- Sanity report — registry rows that still have a NULL email despite
-- the linked user having one. (Should be zero after this migration.)
SELECT r.index_number, r.full_name, r.email AS registry_email, u.email AS user_email
FROM   `student_registry` r
JOIN   `users` u
       ON u.index_number = r.index_number
      AND u.role = 'student'
WHERE  (r.email IS NULL OR r.email = '')
  AND  u.email IS NOT NULL
  AND  u.email <> '';

COMMIT;
