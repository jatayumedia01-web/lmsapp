-- Advanced admin features: user moderation, Q&A moderation,
-- runtime-editable subscription plans, and a key-value settings store.

-- 1) User moderation columns. NULL by default → existing rows untouched.
ALTER TABLE users
    ADD COLUMN is_banned       TINYINT(1)   NOT NULL DEFAULT 0 AFTER role,
    ADD COLUMN banned_at       DATETIME     NULL              AFTER is_banned,
    ADD COLUMN banned_reason   VARCHAR(255) NULL              AFTER banned_at,
    ADD INDEX  idx_users_banned (is_banned);

-- 2) Q&A moderation columns. Default APPROVED so existing questions stay
--    visible after migration. New code can flip this to PENDING for incoming
--    questions if pre-moderation is enabled in settings.
ALTER TABLE lesson_questions
    ADD COLUMN moderation_status ENUM('PENDING','APPROVED','REJECTED','SPAM')
                                  NOT NULL DEFAULT 'APPROVED' AFTER is_pinned,
    ADD COLUMN moderated_at      DATETIME     NULL            AFTER moderation_status,
    ADD COLUMN moderated_by      VARCHAR(64)  NULL            AFTER moderated_at,
    ADD INDEX  idx_questions_mod (moderation_status, created_at);

-- 3) Runtime-editable subscription plans. Code can fall back to a static
--    catalog if the table is empty, but admins typically curate it here.
CREATE TABLE IF NOT EXISTS subscription_plans (
    id                  VARCHAR(64) PRIMARY KEY,
    name                VARCHAR(100) NOT NULL,
    description         TEXT         NOT NULL,
    price_monthly_cents INT          NOT NULL DEFAULT 0,
    price_yearly_cents  INT          NOT NULL DEFAULT 0,
    currency            VARCHAR(8)   NOT NULL DEFAULT 'INR',
    trial_days          INT          NOT NULL DEFAULT 0,
    features_json       TEXT         NOT NULL,
    sort_order          INT          NOT NULL DEFAULT 0,
    is_active           TINYINT(1)   NOT NULL DEFAULT 1,
    is_default          TINYINT(1)   NOT NULL DEFAULT 0,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_plans_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) App settings — typed key/value store. Nothing else in the codebase
--    should hard-code config that admins might want to change at runtime.
CREATE TABLE IF NOT EXISTS app_settings (
    `key`        VARCHAR(100) PRIMARY KEY,
    `value`      TEXT         NULL,
    value_type   ENUM('STRING','INT','BOOL','JSON') NOT NULL DEFAULT 'STRING',
    `group`      VARCHAR(50)  NOT NULL DEFAULT 'general',
    label        VARCHAR(190) NOT NULL,
    description  VARCHAR(500) NULL,
    is_secret    TINYINT(1)   NOT NULL DEFAULT 0,
    sort_order   INT          NOT NULL DEFAULT 0,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_settings_group (`group`, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the settings rows that the admin UI knows how to render. Existing
-- values are preserved on re-run.
INSERT IGNORE INTO app_settings (`key`, `value`, value_type, `group`, label, description, is_secret, sort_order) VALUES
    -- General
    ('app_name',           'Devithor LMS',              'STRING','general', 'App name',         'Shown in browser title and emails.',                  0, 10),
    ('app_logo_url',       '',                          'STRING','general', 'Logo URL',         'Public URL of your logo image (PNG/SVG).',            0, 20),
    ('support_email',      'support@apptesting.in',     'STRING','general', 'Support email',    'Where learners can reach you for help.',              0, 30),
    ('contact_phone',      '',                          'STRING','general', 'Contact phone',    'Optional, shown on the app About screen.',            0, 40),
    ('default_currency',   'INR',                       'STRING','general', 'Default currency', 'ISO 4217 code, e.g. INR, USD.',                       0, 50),
    -- Features
    ('signups_enabled',    '1',                         'BOOL',  'features','Signups enabled',  'Turn off to stop new account creation.',              0, 10),
    ('free_trial_days',    '7',                         'INT',   'features','Free trial days',  'Days a new account stays on TRIALING.',               0, 20),
    ('max_free_courses',   '3',                         'INT',   'features','Max free courses', 'How many premium courses non-paying users can open.', 0, 30),
    ('qa_premoderation',   '0',                         'BOOL',  'features','Pre-moderate Q&A', 'New questions wait for admin approval before showing.',0, 40),
    -- Notifications
    ('notif_email_enabled','1',                         'BOOL',  'notifications','Email notifications', 'Master switch for outgoing email.',           0, 10),
    ('notif_push_enabled', '1',                         'BOOL',  'notifications','Push notifications',  'Master switch for FCM push.',                  0, 20),
    ('smtp_host',          '',                          'STRING','notifications','SMTP host',           'e.g. smtp.gmail.com',                          0, 30),
    ('smtp_port',          '587',                       'INT',   'notifications','SMTP port',           '',                                             0, 40),
    ('smtp_username',      '',                          'STRING','notifications','SMTP username',       '',                                             0, 50),
    ('smtp_password',      '',                          'STRING','notifications','SMTP password',       '',                                             1, 60),
    ('fcm_server_key',     '',                          'STRING','notifications','FCM server key',      'Firebase Cloud Messaging legacy server key.',  1, 70),
    -- Payments
    ('razorpay_key_id',    '',                          'STRING','payments', 'Razorpay key id',  '',                                                    0, 10),
    ('razorpay_key_secret','',                          'STRING','payments', 'Razorpay key secret','',                                                  1, 20),
    ('stripe_public_key',  '',                          'STRING','payments', 'Stripe public key','',                                                    0, 30),
    ('stripe_secret_key',  '',                          'STRING','payments', 'Stripe secret key','',                                                    1, 40),
    -- Maintenance
    ('maintenance_mode',   '0',                         'BOOL',  'maintenance','Maintenance mode', 'Block all non-admin requests.',                    0, 10),
    ('maintenance_message','We are upgrading. Back shortly.', 'STRING','maintenance','Maintenance message','Shown on the maintenance screen.',         0, 20);
