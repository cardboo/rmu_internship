-- =====================================================================
-- RMU Internship Tracker - v2 Migration 010
-- File: migrations/010_evaluations.sql
--
-- Stores the result of the on-the-job supervisor's final evaluation.
-- Mirrors the official RMU "INDUSTRIAL ATTACHMENT EVALUATION FORM":
--
--   1. Acceptance of responsibility               max 5
--   2. Reliability under pressure                 max 5
--   3. Application of professional knowledge      max 5
--   4. Output of work                             max 5
--   5. Quality of work                            max 5
--   6. Punctuality                                max 5
--   7. Overall performance                        max 10
--   8. Overall conduct (ethics)                   max 10
--                                              TOTAL 50
--
-- One evaluation per placement (UNIQUE on placement_id).
-- =====================================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `evaluations` (
    `id`                    INT(11)      NOT NULL AUTO_INCREMENT,
    `placement_id`          INT(11)      NOT NULL,

    -- Eight criteria, score-as-stored (the PHP layer enforces the cap).
    `score_responsibility`   TINYINT(3)  NOT NULL DEFAULT 0,
    `score_reliability`      TINYINT(3)  NOT NULL DEFAULT 0,
    `score_knowledge`        TINYINT(3)  NOT NULL DEFAULT 0,
    `score_output`           TINYINT(3)  NOT NULL DEFAULT 0,
    `score_quality`          TINYINT(3)  NOT NULL DEFAULT 0,
    `score_punctuality`      TINYINT(3)  NOT NULL DEFAULT 0,
    `score_overall_perf`     TINYINT(3)  NOT NULL DEFAULT 0,
    `score_overall_conduct`  TINYINT(3)  NOT NULL DEFAULT 0,

    -- Cached sum (computed in PHP at insert/update — avoids GENERATED
    -- column compatibility quirks across MariaDB versions).
    `total_score`           TINYINT(3)   NOT NULL DEFAULT 0,

    -- Supervisor identity at submission (snapshot, in case the
    -- placement record is later edited).
    `supervisor_name`       VARCHAR(150) NOT NULL,
    `organization`          VARCHAR(150) NOT NULL,
    `supervisor_title`      VARCHAR(100) DEFAULT NULL,

    `submitted_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `submitted_ip`          VARCHAR(45)  DEFAULT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_eval_placement` (`placement_id`),
    KEY `idx_eval_total` (`total_score`),
    CONSTRAINT `fk_eval_placement`
        FOREIGN KEY (`placement_id`) REFERENCES `placements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
