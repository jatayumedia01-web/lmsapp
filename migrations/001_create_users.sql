-- Users table — both learners (role='STUDENT') and admins (role='ADMIN').
-- Password is nullable: students can sign in passwordlessly via the mobile
-- "email + name" flow (matches the current Android AuthScreen). Admins must
-- have a password.

CREATE TABLE IF NOT EXISTS users (
    id              VARCHAR(64) PRIMARY KEY,
    email           VARCHAR(190) NOT NULL UNIQUE,
    full_name       VARCHAR(190) NOT NULL,
    role            ENUM('STUDENT', 'INSTRUCTOR', 'ADMIN', 'PARENT') NOT NULL DEFAULT 'STUDENT',
    password_hash   VARCHAR(255) NULL,
    avatar_url      VARCHAR(500) NULL,
    tenant_id       VARCHAR(64) NOT NULL DEFAULT 'default',
    xp              INT NOT NULL DEFAULT 0,
    streak_days     INT NOT NULL DEFAULT 0,
    joined_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_sign_in_at DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
