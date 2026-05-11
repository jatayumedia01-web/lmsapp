-- Migration 012: OTP-based authentication and extended student profile fields.
--
-- Adds the `auth_otps` table for passwordless email OTP flow, and extends the
-- `users` table with personal profile, education, and onboarding columns that
-- the Android onboarding screens collect.
--
-- Each ALTER TABLE is a separate statement so the migrate.php runner (which
-- splits on ";\s*$" per line) processes them one at a time. If a column
-- already exists MySQL will throw a 1060 error; the runner catches Throwable
-- and aborts — so this migration is safe to run only once on a fresh schema.

-- ── OTP table ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS auth_otps (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(190)   NOT NULL,
    otp_code    VARCHAR(10)    NOT NULL,
    expires_at  DATETIME       NOT NULL,
    used        TINYINT(1)     NOT NULL DEFAULT 0,
    attempts    INT            NOT NULL DEFAULT 0,
    ip          VARCHAR(45)    NULL,
    created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_otp_lookup (email, used, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Profile columns on users ─────────────────────────────────────────────────
-- Run each ALTER separately so the migration runner can catch a duplicate-
-- column error on individual statements if needed.

ALTER TABLE users ADD COLUMN dob DATE NULL;

ALTER TABLE users ADD COLUMN gender ENUM('MALE','FEMALE','OTHER','PREFER_NOT_TO_SAY') NULL;

ALTER TABLE users ADD COLUMN mobile VARCHAR(20) NULL;

ALTER TABLE users ADD COLUMN whatsapp VARCHAR(20) NULL;

ALTER TABLE users ADD COLUMN school_name VARCHAR(255) NULL;

ALTER TABLE users ADD COLUMN class_id VARCHAR(64) NULL;

ALTER TABLE users ADD COLUMN city VARCHAR(100) NULL;

ALTER TABLE users ADD COLUMN state VARCHAR(100) NULL;

ALTER TABLE users ADD COLUMN address TEXT NULL;

ALTER TABLE users ADD COLUMN onboarding_completed TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE users ADD COLUMN profile_picture_url VARCHAR(500) NULL;

-- ── Ban columns (added in case older schema is missing them) ─────────────────
ALTER TABLE users ADD COLUMN is_banned TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE users ADD COLUMN banned_at DATETIME NULL;

ALTER TABLE users ADD COLUMN banned_reason VARCHAR(500) NULL;
