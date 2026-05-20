-- =====================================================================
-- RMU Internship Tracker - v2 Migration 017
-- File: migrations/017_reminders_log.sql
--
-- Records every reminder email the cron-driven send_reminders.php
-- dispatches. Used to dedupe (don't re-send "logbook overdue" daily
-- for the same student-week) and to surface a "reminders sent" report
-- on the admin dashboard.
--
-- kind          = the rule that fired ('logbook_overdue',
--                 'evaluation_due_soon', 'hod_letter_digest', ...)
-- target_type   = 'user' (student/HOD recipient) or 'placement'
-- target_id     = id of the target row
-- dedupe_key    = stable per-event key so we can ignore duplicates
--                 within a window. Examples:
--                   'logbook_overdue:placement=42:weekISO=2026-W19'
--                   'eval_due_soon:placement=42:7d'
--                   'hod_letter_digest:hod=7:2026-05-20'
-- delivered     = whether SMTP send_email() returned ok=true
--
-- Idempotent: CREATE TABLE IF NOT EXISTS.
-- =====================================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `reminders_log` (
    `id`            BIGINT       NOT NULL AUTO_INCREMENT,
    `kind`          VARCHAR(40)  NOT NULL,
    `target_type`   VARCHAR(20)  NULL DEFAULT NULL,
    `target_id`     INT          NULL DEFAULT NULL,
    `recipient`     VARCHAR(150) NOT NULL,
    `subject`       VARCHAR(200) NOT NULL,
    `dedupe_key`    VARCHAR(160) NOT NULL,
    `delivered`     TINYINT(1)   NOT NULL DEFAULT 0,
    `error`         VARCHAR(255) NULL DEFAULT NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rem_dedupe` (`dedupe_key`),
    KEY `idx_rem_kind`    (`kind`),
    KEY `idx_rem_target`  (`target_type`, `target_id`),
    KEY `idx_rem_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
