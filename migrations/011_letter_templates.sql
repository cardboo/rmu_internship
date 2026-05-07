-- =====================================================================
-- RMU Internship Tracker - v2 Migration 011
-- File: migrations/011_letter_templates.sql
--
-- Moves the introduction-letter body out of api/generate_letter.php
-- and into a database table so admins can edit it from the UI and
-- vary the wording by department, academic year, and / or semester.
--
-- Match precedence at letter-generation time (most-specific wins):
--    dept + year + semester
--    dept + year
--    dept
--    "default" (all three NULL)
--
-- Available placeholders (replaced before render):
--    {student_name}      {student_index}     {student_program}
--    {department}        {company_name}      {company_address}
--    {start_date}        {end_date}          {weeks}
--    {hod_name}          {hod_title}
--    {academic_year}     {semester}          {date}
-- =====================================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `letter_templates` (
    `id`               INT(11)      NOT NULL AUTO_INCREMENT,
    `name`             VARCHAR(150) NOT NULL,
    `department_id`    INT(11)      DEFAULT NULL,
    `academic_year_id` INT(11)      DEFAULT NULL,
    `semester_id`      INT(11)      DEFAULT NULL,
    `body`             TEXT         NOT NULL,
    `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lt_dept` (`department_id`),
    KEY `idx_lt_year` (`academic_year_id`),
    KEY `idx_lt_sem`  (`semester_id`),
    CONSTRAINT `fk_lt_dept`
        FOREIGN KEY (`department_id`)    REFERENCES `departments`     (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lt_year`
        FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lt_sem`
        FOREIGN KEY (`semester_id`)      REFERENCES `semesters`       (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Seed: a single "default" template that mirrors the wording the
-- old hardcoded letter used. Admins can clone-and-customise per
-- department / year from the new admin/letter_templates.php UI.
-- ---------------------------------------------------------------------
INSERT INTO `letter_templates` (`name`, `body`)
SELECT 'Default attachment letter',
       'We wish to introduce the above-named student who is currently pursuing a program in {student_program} at this University. As part of the requirements for the award of a degree, students are required to undergo a {weeks}-week industrial attachment to gain practical experience.\n\nWe would be grateful if you could offer the student the opportunity to train with your organization from {start_date} to {end_date}.\n\nWe look forward to a favorable response from you.'
WHERE NOT EXISTS (
    SELECT 1 FROM `letter_templates`
    WHERE `department_id` IS NULL
      AND `academic_year_id` IS NULL
      AND `semester_id` IS NULL
);

COMMIT;
