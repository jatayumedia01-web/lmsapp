-- Migration 012: OTP-based authentication and extended student profile fields.
--
-- Adds the `auth_otps` table for passwordless email OTP flow, and extends the
-- `users` table with personal profile, education, and onboarding columns.
--
-- Uses ALTER TABLE ... ADD COLUMN IF NOT EXISTS (MariaDB 10.0.2+ / MySQL 8.0+)
-- so this migration is fully idempotent — safe to re-run after a partial failure.

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
ALTER TABLE users ADD COLUMN IF NOT EXISTS dob DATE NULL;

ALTER TABLE users ADD COLUMN IF NOT EXISTS gender ENUM('MALE','FEMALE','OTHER','PREFER_NOT_TO_SAY') NULL;

ALTER TABLE users ADD COLUMN IF NOT EXISTS mobile VARCHAR(20) NULL;

ALTER TABLE users ADD COLUMN IF NOT EXISTS whatsapp VARCHAR(20) NULL;

ALTER TABLE users ADD COLUMN IF NOT EXISTS school_name VARCHAR(255) NULL;

ALTER TABLE users ADD COLUMN IF NOT EXISTS class_id VARCHAR(64) NULL;

ALTER TABLE users ADD COLUMN IF NOT EXISTS city VARCHAR(100) NULL;

ALTER TABLE users ADD COLUMN IF NOT EXISTS state VARCHAR(100) NULL;

ALTER TABLE users ADD COLUMN IF NOT EXISTS address TEXT NULL;

ALTER TABLE users ADD COLUMN IF NOT EXISTS onboarding_completed TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture_url VARCHAR(500) NULL;

-- ── Ban columns (added in case older schema is missing them) ─────────────────
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_banned TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE users ADD COLUMN IF NOT EXISTS banned_at DATETIME NULL;

ALTER TABLE users ADD COLUMN IF NOT EXISTS banned_reason VARCHAR(500) NULL;
