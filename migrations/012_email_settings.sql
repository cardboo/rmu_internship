-- =====================================================================
-- RMU Internship Tracker - v2 Migration 012
-- File: migrations/012_email_settings.sql
--
-- Stores SMTP configuration so the admin can edit it from the UI
-- (see admin/email_settings.php) instead of the .ini / .env style.
--
-- Defaults point at Mailpit (localhost:1025) so a fresh install
-- can flip notifications on for local dev WITHOUT immediately
-- emailing real students. Production switches to real SMTP
-- credentials (Gmail app-password, Office 365, RMU mail server).
--
-- The `enabled` flag is OFF by default — emails are no-ops until
-- the admin saves the settings page once.
--
-- Idempotent: INSERT IGNORE.
-- =====================================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `email_settings` (
    `setting_key`   VARCHAR(50)  NOT NULL,
    `setting_value` VARCHAR(500) NOT NULL DEFAULT '',
    `is_secret`     TINYINT(1)   NOT NULL DEFAULT 0,
    `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `email_settings` (`setting_key`, `setting_value`, `is_secret`) VALUES
('enabled',       '0',                          0),
('smtp_host',     'localhost',                  0),
('smtp_port',     '1025',                       0),
('smtp_secure',   'none',                       0),
('smtp_user',     '',                           0),
('smtp_pass',     '',                           1),
('from_address',  'noreply@rmu.edu.gh',         0),
('from_name',     'RMU Internship Portal',      0),
('test_to',       '',                           0),
('last_test_at',  '',                           0),
('last_test_msg', '',                           0);

COMMIT;
