-- =====================================================================
-- RMU Internship Tracker - v2 Migration 006
-- File: migrations/006_registry_email_and_domain_fix.sql
--
-- Two related quick-wins:
--
-- 1. Registry roster gets an `email` column so secretaries can
--    pre-populate the student's email at registry-upload time.
--    Both api/registry_lookup.php and secretary/register_student.php
--    will then auto-fill the email field once the index # is entered.
--
-- 2. Fix the seed email domain typo. The correct RMU student domain
--    is `st.rmu.edu.gh`, not `st.edu.rmu.gh` (and there's also some
--    legacy `student.rmu.edu.gh` from the original v1 seed).
--    Migrate any existing rows so they line up with the corrected
--    constant in includes/auth.php.
--
-- Idempotent: column add is guarded; UPDATEs are domain-specific so
-- re-running is a no-op.
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1. student_registry.email
-- ---------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'student_registry'
      AND COLUMN_NAME  = 'email'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE `student_registry` ADD COLUMN `email` VARCHAR(100) DEFAULT NULL AFTER `full_name`',
    'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- 2. Fix the email domain on existing user rows.
--    Two wrong forms have been observed in seed data:
--        @st.edu.rmu.gh        -> typo
--        @student.rmu.edu.gh   -> early-draft format
--    Both should become @st.rmu.edu.gh.
-- ---------------------------------------------------------------------
UPDATE `users`
SET    `email` = REPLACE(`email`, '@st.edu.rmu.gh', '@st.rmu.edu.gh')
WHERE  `email` LIKE '%@st.edu.rmu.gh';

UPDATE `users`
SET    `email` = REPLACE(`email`, '@student.rmu.edu.gh', '@st.rmu.edu.gh')
WHERE  `email` LIKE '%@student.rmu.edu.gh';

-- Sanity report
SELECT
    SUM(`email` LIKE '%@st.rmu.edu.gh')  AS students_with_correct_domain,
    SUM(`email` LIKE '%@st.edu.rmu.gh')  AS still_typo,
    SUM(`email` LIKE '%@student.rmu.edu.gh') AS still_legacy
FROM `users`;

COMMIT;
