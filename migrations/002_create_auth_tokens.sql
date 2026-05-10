-- Mobile API bearer tokens. We store SHA-256 of the token, never the plaintext,
-- so a database leak doesn't immediately let an attacker authenticate.

CREATE TABLE IF NOT EXISTS auth_tokens (
    id          BIGINT AUTO_INCREMENT PRIMARY KEY,
    token_hash  CHAR(64) NOT NULL UNIQUE,
    user_id     VARCHAR(64) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at  DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    INDEX idx_auth_tokens_user (user_id),
    INDEX idx_auth_tokens_expiry (expires_at),
    CONSTRAINT fk_auth_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
