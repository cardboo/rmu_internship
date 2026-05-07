-- =====================================================================
-- RMU Internship Tracker - v2 Migration 009
-- File: migrations/009_logbook_digital.sql
--
-- Replaces the PDF-upload weekly log with a structured digital one.
-- The existing logbooks table becomes the "weekly entry header" and
-- a new logbook_days child table holds the per-day activities.
--
-- Mirrors the official RMU weekly log PDF:
--   Header: Name, Programme, Index, Organisation, Department
--           (auto-filled from user + placement at render time)
--   Week beginning ... Week ending ...
--   5 rows of (Day/Date, Activities Undertaken)
--   Student's Remarks
--   Supervisor's Remarks (filled by the on-the-job supervisor
--                          via token in Sprint 4)
--   Name of Supervisor / Status (also filled by supervisor)
--
-- Idempotent: ALTERs are guarded; CREATE TABLE IF NOT EXISTS.
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1. Extend logbooks
-- ---------------------------------------------------------------------

-- placement_id (which placement the week belongs to)
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='logbooks' AND COLUMN_NAME='placement_id');
SET @ddl := IF(@col = 0,
    'ALTER TABLE `logbooks` ADD COLUMN `placement_id` INT(11) NULL AFTER `student_id`',
    'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- student_remarks
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='logbooks' AND COLUMN_NAME='student_remarks');
SET @ddl := IF(@col = 0,
    'ALTER TABLE `logbooks` ADD COLUMN `student_remarks` TEXT NULL',
    'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- supervisor_remarks  (filled by the on-the-job supervisor via Sprint 4 token)
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='logbooks' AND COLUMN_NAME='supervisor_remarks');
SET @ddl := IF(@col = 0,
    'ALTER TABLE `logbooks` ADD COLUMN `supervisor_remarks` TEXT NULL',
    'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- supervisor_signed_at  (timestamp the supervisor confirmed the log)
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='logbooks' AND COLUMN_NAME='supervisor_signed_at');
SET @ddl := IF(@col = 0,
    'ALTER TABLE `logbooks` ADD COLUMN `supervisor_signed_at` TIMESTAMP NULL DEFAULT NULL',
    'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- supervisor_signed_by_name  (snapshot of the supervisor's name at sign time)
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='logbooks' AND COLUMN_NAME='supervisor_signed_by_name');
SET @ddl := IF(@col = 0,
    'ALTER TABLE `logbooks` ADD COLUMN `supervisor_signed_by_name` VARCHAR(150) NULL',
    'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- supervisor_signed_by_status
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='logbooks' AND COLUMN_NAME='supervisor_signed_by_status');
SET @ddl := IF(@col = 0,
    'ALTER TABLE `logbooks` ADD COLUMN `supervisor_signed_by_status` VARCHAR(100) NULL',
    'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- is_submitted  (0 = draft, 1 = submitted/locked-by-student)
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='logbooks' AND COLUMN_NAME='is_submitted');
SET @ddl := IF(@col = 0,
    'ALTER TABLE `logbooks` ADD COLUMN `is_submitted` TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- 2. logbook_days (one row per day of a logbook week)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `logbook_days` (
    `id`         INT(11)     NOT NULL AUTO_INCREMENT,
    `logbook_id` INT(11)     NOT NULL,
    `day_label`  VARCHAR(20) NOT NULL,    -- 'Monday','Tuesday',...
    `day_date`   DATE        NOT NULL,
    `activities` TEXT        NULL,
    `sort_order` INT(11)     NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_lbd_logbook` (`logbook_id`),
    CONSTRAINT `fk_lbd_logbook`
        FOREIGN KEY (`logbook_id`) REFERENCES `logbooks` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- 3. Best-effort placement_id backfill (most recent placement per student)
-- ---------------------------------------------------------------------
UPDATE `logbooks` l
JOIN (
    SELECT student_id, MAX(id) AS pid FROM `placements` GROUP BY student_id
) p ON p.student_id = l.student_id
SET l.placement_id = p.pid
WHERE l.placement_id IS NULL;

COMMIT;
