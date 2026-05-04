-- =====================================================================
-- RMU Internship Tracker - v2 Foundation Migration
-- File: migrations/001_v2_foundation.sql
--
-- Creates:
--   * departments        : canonical list of academic departments
--   * programs           : programs of study, FK -> departments
--   * student_registry   : authoritative roster of admitted students
--                          (uploaded by Registry; secretaries pull from
--                          here to register student accounts)
--
-- Idempotent: safe to run more than once.
-- Run via phpMyAdmin or:
--   mysql -u root internship_system < migrations/001_v2_foundation.sql
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- departments
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `departments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(20) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_dept_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- programs
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `programs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `department_id` INT(11) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(20) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_dept_program` (`department_id`,`name`),
  KEY `idx_program_dept` (`department_id`),
  CONSTRAINT `fk_program_dept`
    FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- student_registry
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `student_registry` (
  `index_number`    VARCHAR(20)  NOT NULL,
  `full_name`       VARCHAR(100) NOT NULL,
  `department_id`   INT(11)      NOT NULL,
  `program_id`      INT(11)      NOT NULL,
  `level`           VARCHAR(10)  DEFAULT NULL,
  `gender`          ENUM('Male','Female') DEFAULT NULL,
  `date_of_birth`   DATE         DEFAULT NULL,
  `year_admitted`   VARCHAR(10)  DEFAULT NULL,
  `is_claimed`      TINYINT(1)   NOT NULL DEFAULT 0,
  `claimed_user_id` INT(11)      DEFAULT NULL,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`index_number`),
  KEY `idx_reg_dept`    (`department_id`),
  KEY `idx_reg_program` (`program_id`),
  KEY `idx_reg_user`    (`claimed_user_id`),
  CONSTRAINT `fk_reg_dept`
    FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `fk_reg_program`
    FOREIGN KEY (`program_id`)    REFERENCES `programs` (`id`),
  CONSTRAINT `fk_reg_user`
    FOREIGN KEY (`claimed_user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Seed: departments (from observed values in users table)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `departments` (`name`, `code`) VALUES
  ('ICT',                'ICT'),
  ('Marine Engineering', 'MAR'),
  ('Nautical Science',   'NAU'),
  ('Transport',          'TRP'),
  ('Electrical',         'ELE'),
  ('Mechanical',         'MEC'),
  ('Accounting',         'ACC');

-- ---------------------------------------------------------------------
-- Seed: programs (mapped from observed program names)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `programs` (`department_id`, `name`, `code`)
SELECT d.id, p.name, p.code
FROM (
  SELECT 'ICT'                AS dept, 'BSc. Information Technology'             AS name, 'BIT' AS code UNION ALL
  SELECT 'ICT',                       'BSc. Computer Science',                          'BCS'         UNION ALL
  SELECT 'Marine Engineering',        'BSc. Marine Engineering',                        'BME'         UNION ALL
  SELECT 'Nautical Science',          'BSc. Nautical Science',                          'BNS'         UNION ALL
  SELECT 'Transport',                 'BSc. Port & Shipping Administration',            'BPS'         UNION ALL
  SELECT 'Electrical',                'BSc. Electrical & Electronic Engineering',       'BEE'         UNION ALL
  SELECT 'Mechanical',                'BSc. Mechanical Engineering',                    'BMT'         UNION ALL
  SELECT 'Accounting',                'BSc. Accounting',                                'BAC'
) p
JOIN `departments` d ON d.name = p.dept;

COMMIT;
