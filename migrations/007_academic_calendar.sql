-- =====================================================================
-- RMU Internship Tracker - v2 Migration 007
-- File: migrations/007_academic_calendar.sql
--
-- Sets up the academic calendar foundation. Replaces the loose
-- key/value rows in `settings` (academic_year_name, sem1_start, etc.)
-- with proper relational tables that can hold every past calendar
-- alongside the current one.
--
-- New tables:
--   academic_years   one row per academic year, only one is_current
--   semesters        rows belong to an academic year (RMU has 2/year)
--
-- New FK columns added to existing tables (nullable for now so the
-- migration is non-destructive — every dated row gets backfilled
-- with the seed academic year):
--   requests.academic_year_id
--   logbooks.academic_year_id
--   internship_submissions.academic_year_id
--   student_registry.academic_year_id_admitted
--
-- Idempotent: re-runnable without data loss.
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1. academic_years
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `academic_years` (
    `id`           INT(11)      NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(20)  NOT NULL,         -- e.g. '2025/2026'
    `start_date`   DATE         NOT NULL,
    `end_date`     DATE         NOT NULL,
    `is_current`   TINYINT(1)   NOT NULL DEFAULT 0,
    `is_archived`  TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_acyear_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 2. semesters
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `semesters` (
    `id`               INT(11)     NOT NULL AUTO_INCREMENT,
    `academic_year_id` INT(11)     NOT NULL,
    `label`            VARCHAR(50) NOT NULL,    -- 'Semester 1', 'Semester 2'
    `start_date`       DATE        NOT NULL,
    `end_date`         DATE        NOT NULL,
    `sort_order`       INT(11)     NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_year_label` (`academic_year_id`, `label`),
    KEY `idx_sem_year` (`academic_year_id`),
    CONSTRAINT `fk_sem_year`
        FOREIGN KEY (`academic_year_id`)
        REFERENCES `academic_years` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 3. Seed the first academic year + its two semesters from the
--    existing `settings` rows. Defaults match the seed dump.
-- ---------------------------------------------------------------------
SET @ay_name  := COALESCE((SELECT setting_value FROM `settings` WHERE setting_key='academic_year_name'), '2025/2026');
SET @ay_start := COALESCE((SELECT setting_value FROM `settings` WHERE setting_key='sem1_start'),         '2025-09-01');
SET @ay_end   := COALESCE((SELECT setting_value FROM `settings` WHERE setting_key='sem2_end'),           '2026-05-30');
SET @sem1_s   := COALESCE((SELECT setting_value FROM `settings` WHERE setting_key='sem1_start'),         '2025-09-01');
SET @sem1_e   := COALESCE((SELECT setting_value FROM `settings` WHERE setting_key='sem1_end'),           '2025-12-15');
SET @sem2_s   := COALESCE((SELECT setting_value FROM `settings` WHERE setting_key='sem2_start'),         '2026-01-15');
SET @sem2_e   := COALESCE((SELECT setting_value FROM `settings` WHERE setting_key='sem2_end'),           '2026-05-30');

INSERT IGNORE INTO `academic_years` (`name`, `start_date`, `end_date`, `is_current`)
VALUES (@ay_name, @ay_start, @ay_end, 1);

SET @ay_id := (SELECT `id` FROM `academic_years` WHERE `name` = @ay_name LIMIT 1);

INSERT IGNORE INTO `semesters` (`academic_year_id`, `label`, `start_date`, `end_date`, `sort_order`)
VALUES (@ay_id, 'Semester 1', @sem1_s, @sem1_e, 1);

INSERT IGNORE INTO `semesters` (`academic_year_id`, `label`, `start_date`, `end_date`, `sort_order`)
VALUES (@ay_id, 'Semester 2', @sem2_s, @sem2_e, 2);

-- ---------------------------------------------------------------------
-- 4. Add academic_year_id columns to existing dated tables.
--    All adds are guarded by an information_schema check so the
--    migration is safe to re-run.
-- ---------------------------------------------------------------------

-- requests
SET @col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'requests' AND COLUMN_NAME = 'academic_year_id'
);
SET @ddl := IF(@col = 0,
    'ALTER TABLE `requests` ADD COLUMN `academic_year_id` INT(11) NULL AFTER `status`',
    'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
UPDATE `requests` SET `academic_year_id` = @ay_id WHERE `academic_year_id` IS NULL;

-- logbooks
SET @col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'logbooks' AND COLUMN_NAME = 'academic_year_id'
);
SET @ddl := IF(@col = 0,
    'ALTER TABLE `logbooks` ADD COLUMN `academic_year_id` INT(11) NULL',
    'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
UPDATE `logbooks` SET `academic_year_id` = @ay_id WHERE `academic_year_id` IS NULL;

-- internship_submissions
SET @col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'internship_submissions' AND COLUMN_NAME = 'academic_year_id'
);
SET @ddl := IF(@col = 0,
    'ALTER TABLE `internship_submissions` ADD COLUMN `academic_year_id` INT(11) NULL',
    'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
UPDATE `internship_submissions` SET `academic_year_id` = @ay_id WHERE `academic_year_id` IS NULL;

-- student_registry (admitted in)
SET @col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'student_registry' AND COLUMN_NAME = 'academic_year_id_admitted'
);
SET @ddl := IF(@col = 0,
    'ALTER TABLE `student_registry` ADD COLUMN `academic_year_id_admitted` INT(11) NULL',
    'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
UPDATE `student_registry` SET `academic_year_id_admitted` = @ay_id WHERE `academic_year_id_admitted` IS NULL;

-- Sanity report
SELECT
    (SELECT COUNT(*) FROM `academic_years`)                         AS years_count,
    (SELECT COUNT(*) FROM `semesters` WHERE academic_year_id=@ay_id) AS seed_year_semesters,
    @ay_name                                                         AS seed_year,
    (SELECT COUNT(*) FROM `requests`  WHERE academic_year_id=@ay_id) AS requests_tagged,
    (SELECT COUNT(*) FROM `logbooks`  WHERE academic_year_id=@ay_id) AS logbooks_tagged,
    (SELECT COUNT(*) FROM `internship_submissions` WHERE academic_year_id=@ay_id) AS submissions_tagged;

COMMIT;
