-- =====================================================================
-- RMU Internship Tracker - v2 Migration 003
-- File: migrations/003_v2_user_admin.sql
--
-- Adds the columns and tables needed for the next batch of supervisor
-- requirements:
--   * Force-password-change on first login          -> users.must_change_password
--   * Archive instead of delete                     -> users.is_archived, users.archived_at
--   * Job title is a dropdown, admin-managed        -> new job_titles table + seed
--
-- Idempotent: column adds are guarded; INSERTs use IGNORE.
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- users: must_change_password
-- ---------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'users'
      AND COLUMN_NAME  = 'must_change_password'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password`',
    'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- users: is_archived
-- ---------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'users'
      AND COLUMN_NAME  = 'is_archived'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `is_archived` TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- users: archived_at
-- ---------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'users'
      AND COLUMN_NAME  = 'archived_at'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `archived_at` TIMESTAMP NULL DEFAULT NULL',
    'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------
-- job_titles
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `job_titles` (
    `id`             INT(11) NOT NULL AUTO_INCREMENT,
    `name`           VARCHAR(150) NOT NULL,
    `applies_to`     ENUM('staff','hod','secretary','any') NOT NULL DEFAULT 'staff',
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_job_title_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed: a sensible starter list. Admin can extend via the UI later.
INSERT IGNORE INTO `job_titles` (`name`, `applies_to`) VALUES
    ('Head of Department',                  'hod'),
    ('Acting Head of Department',           'hod'),
    ('Department Secretary',                'secretary'),
    ('Senior Lecturer',                     'staff'),
    ('Lecturer',                            'staff'),
    ('Assistant Lecturer',                  'staff'),
    ('Professor',                           'staff'),
    ('Associate Professor',                 'staff'),
    ('Tutor',                               'staff'),
    ('Industrial Liaison Officer',          'staff'),
    ('System Administrator',                'any');

COMMIT;
