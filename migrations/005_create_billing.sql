-- Subscriptions, payment methods, invoices. The "plans" themselves stay
-- application-config (matching Android's PlansCatalog) — admins can later
-- promote them to a DB table if Plans need to be edited at runtime.

CREATE TABLE IF NOT EXISTS subscriptions (
    user_id              VARCHAR(64) PRIMARY KEY,
    plan_id              VARCHAR(64) NOT NULL,
    status               ENUM('FREE', 'TRIALING', 'ACTIVE', 'CANCELLED', 'PAST_DUE') NOT NULL,
    billing_cycle        ENUM('MONTHLY', 'YEARLY') NOT NULL DEFAULT 'MONTHLY',
    started_at_millis    BIGINT NOT NULL,
    renews_at_millis     BIGINT NOT NULL DEFAULT 0,
    trial_ends_at_millis BIGINT NULL,
    auto_renew           TINYINT(1) NOT NULL DEFAULT 0,
    payment_method_id    VARCHAR(64) NULL,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subscriptions_status (status),
    CONSTRAINT fk_subscriptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_methods (
    id                  VARCHAR(64) PRIMARY KEY,
    user_id             VARCHAR(64) NOT NULL,
    type                ENUM('CARD', 'UPI', 'GOOGLE_PAY', 'PAYPAL') NOT NULL,
    brand               VARCHAR(50) NOT NULL DEFAULT '',
    last4               VARCHAR(60) NOT NULL DEFAULT '',
    expiry_month        TINYINT NOT NULL DEFAULT 0,
    expiry_year         SMALLINT NOT NULL DEFAULT 0,
    holder_name         VARCHAR(190) NOT NULL DEFAULT '',
    is_default          TINYINT(1) NOT NULL DEFAULT 0,
    added_at_millis     BIGINT NOT NULL,
    INDEX idx_payment_methods_user (user_id, is_default),
    CONSTRAINT fk_payment_methods_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
    id                          VARCHAR(64) PRIMARY KEY,
    user_id                     VARCHAR(64) NOT NULL,
    number                      VARCHAR(50) NOT NULL UNIQUE,
    date_millis                 BIGINT NOT NULL,
    amount_cents                INT NOT NULL,
    currency                    VARCHAR(8) NOT NULL DEFAULT 'USD',
    status                      ENUM('PAID', 'PENDING', 'FAILED', 'REFUNDED') NOT NULL DEFAULT 'PAID',
    plan_name                   VARCHAR(100) NOT NULL,
    billing_cycle_label         VARCHAR(50) NOT NULL,
    period_start_millis         BIGINT NOT NULL,
    period_end_millis           BIGINT NOT NULL,
    payment_method_last4        VARCHAR(60) NULL,
    INDEX idx_invoices_user (user_id, date_millis),
    CONSTRAINT fk_invoices_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coupons (
    id                  BIGINT AUTO_INCREMENT PRIMARY KEY,
    code                VARCHAR(50) NOT NULL UNIQUE,
    description         VARCHAR(255) NOT NULL,
    discount_percent    TINYINT NULL,
    discount_cents      INT NULL,
    expires_at_millis   BIGINT NULL,
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
