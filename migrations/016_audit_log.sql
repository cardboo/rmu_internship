-- =====================================================================
-- RMU Internship Tracker - v2 Migration 016
-- File: migrations/016_audit_log.sql
--
-- Append-only audit trail for sensitive admin actions. Captures the
-- actor (user id + role), the action verb, the affected target, and a
-- JSON payload of relevant context. Indexed for the common query
-- shapes used by admin/audit.php (filter by actor, action, target,
-- created_at range).
--
-- actor_user_id is NULL for system-triggered events (e.g. background
-- cleanup jobs); actor_role is denormalised so we still know who did
-- what after a user's role has changed.
--
-- Idempotent: CREATE TABLE IF NOT EXISTS.
-- =====================================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`            BIGINT       NOT NULL AUTO_INCREMENT,
    `actor_user_id` INT          NULL DEFAULT NULL,
    `actor_role`    VARCHAR(20)  NULL DEFAULT NULL,
    `action`        VARCHAR(64)  NOT NULL,
    `target_type`   VARCHAR(40)  NULL DEFAULT NULL,
    `target_id`     VARCHAR(64)  NULL DEFAULT NULL,
    `payload_json`  TEXT         NULL DEFAULT NULL,
    `ip`            VARCHAR(45)  NULL DEFAULT NULL,
    `ua`            VARCHAR(255) NULL DEFAULT NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_actor`   (`actor_user_id`),
    KEY `idx_audit_action`  (`action`),
    KEY `idx_audit_target`  (`target_type`, `target_id`),
    KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
