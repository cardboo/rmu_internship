-- =====================================================================
-- RMU Internship Tracker - v2 Migration 004
-- File: migrations/004_consolidate_profile_pic.sql
--
-- The users table grew two parallel columns for the same data:
--   * profile_pic   — what admin/profile.php writes to
--   * profile_path  — what student/profile.php writes to
-- Login (index.php) was only reading profile_path, so admin avatars
-- visually disappeared on re-login.
--
-- This migration consolidates everything into profile_pic and treats
-- the seed sentinel 'default.png' (which has no actual file) as NULL.
--
-- profile_path is left in place so existing read sites keep working
-- during the transition; the next migration (005) can drop it after
-- the application code has fully switched over.
-- =====================================================================

START TRANSACTION;

-- 1. If profile_pic is empty/NULL/sentinel, copy the value from
--    profile_path so we don't lose any uploaded student avatars.
UPDATE `users`
SET    `profile_pic` = `profile_path`
WHERE  `profile_path` IS NOT NULL
  AND  `profile_path` <> ''
  AND  (`profile_pic` IS NULL OR `profile_pic` = '' OR `profile_pic` = 'default.png');

-- 2. Drop the seed sentinel so application code only has to handle NULL.
UPDATE `users`
SET    `profile_pic` = NULL
WHERE  `profile_pic` = 'default.png';

-- 3. Sanity check
SELECT
    COUNT(*)                                            AS total_users,
    SUM(`profile_pic` IS NOT NULL AND `profile_pic` <> '') AS with_avatar,
    SUM(`profile_pic` IS NULL OR `profile_pic` = '')    AS without_avatar
FROM `users`;

COMMIT;
