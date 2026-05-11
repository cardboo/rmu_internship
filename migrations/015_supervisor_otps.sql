-- =====================================================================
-- RMU Internship Tracker - v2 Migration 015
-- File: migrations/015_supervisor_otps.sql
--
-- The token-based supervisor portal (supervisor.php / supervisor_
-- evaluation.php) assumed the supervisor could reach the server from
-- outside the LAN. On a local-only deployment that doesn't work, so
-- the supervisor now sits at the student's machine and verifies
-- their identity via a 6-digit OTP emailed to the address captured
-- on the placement record.
--
-- One row per OTP issuance. `purpose` distinguishes between:
--     'logbook:<logbook_id>'   weekly-log remarks for that week
--     'evaluation'             final supervisor evaluation
--
-- code_hash is a bcrypt hash of the 6-digit code so the plaintext
-- never sits in the DB.
--
-- Idempotent: CREATE TABLE IF NOT EXISTS.
-- =====================================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `supervisor_otps` (
    `id`           INT(11)      NOT NULL AUTO_INCREMENT,
    `placement_id` INT(11)      NOT NULL,
    `email`        VARCHAR(150) NOT NULL,
    `purpose`      VARCHAR(60)  NOT NULL,
    `code_hash`    VARCHAR(255) NOT NULL,
    `expires_at`   TIMESTAMP    NOT NULL,
    `consumed_at`  TIMESTAMP    NULL DEFAULT NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_otp_placement` (`placement_id`),
    KEY `idx_otp_purpose`   (`placement_id`, `purpose`),
    KEY `idx_otp_expires`   (`expires_at`),
    CONSTRAINT `fk_otp_placement`
        FOREIGN KEY (`placement_id`) REFERENCES `placements` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
