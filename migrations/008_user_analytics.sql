-- User behavior, device, location and analytics tracking.
--
-- Designed for write-heavy ingest: indexes are tuned for the dashboard
-- read paths (per-user timeline, per-event funnel, per-country breakdown)
-- without bloating insert cost. Pre-aggregated daily / geo tables make the
-- admin dashboards O(rows-in-period) instead of O(events-in-period).

-- 1) Devices: one row per (user, device_id) pair. Apps send a stable UUID
--    they generate on first launch.
CREATE TABLE IF NOT EXISTS user_devices (
    id              VARCHAR(64) PRIMARY KEY,
    user_id         VARCHAR(64) NOT NULL,
    device_id       VARCHAR(128) NOT NULL,
    platform        ENUM('ANDROID','IOS','WEB','OTHER') NOT NULL DEFAULT 'OTHER',
    device_type     VARCHAR(40)  NOT NULL DEFAULT 'unknown',
    os_name         VARCHAR(40)  NOT NULL DEFAULT '',
    os_version      VARCHAR(40)  NOT NULL DEFAULT '',
    app_version     VARCHAR(40)  NOT NULL DEFAULT '',
    model           VARCHAR(120) NOT NULL DEFAULT '',
    manufacturer    VARCHAR(80)  NOT NULL DEFAULT '',
    screen          VARCHAR(40)  NOT NULL DEFAULT '',
    language        VARCHAR(20)  NOT NULL DEFAULT '',
    timezone        VARCHAR(60)  NOT NULL DEFAULT '',
    push_token      VARCHAR(255) NULL,
    first_seen_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_device (user_id, device_id),
    INDEX idx_devices_user (user_id, last_seen_at),
    INDEX idx_devices_platform (platform, last_seen_at),
    CONSTRAINT fk_devices_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) IP geolocation cache: lookup is HTTP-based, expensive. Cached for 7d.
CREATE TABLE IF NOT EXISTS ip_geo_cache (
    ip_address      VARCHAR(45) PRIMARY KEY,
    country_code    CHAR(2)      NULL,
    country         VARCHAR(100) NULL,
    region          VARCHAR(100) NULL,
    city            VARCHAR(100) NULL,
    latitude        DECIMAL(10, 6) NULL,
    longitude       DECIMAL(10, 6) NULL,
    timezone        VARCHAR(60)  NULL,
    isp             VARCHAR(190) NULL,
    lookup_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ipgeo_lookup (lookup_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Sessions: one row per app foreground period. ended_at NULL means
--    "still open" or "no orderly end signal" (network drop, app killed).
CREATE TABLE IF NOT EXISTS user_sessions (
    id                  VARCHAR(64) PRIMARY KEY,
    user_id             VARCHAR(64) NOT NULL,
    device_pk           VARCHAR(64) NULL,
    started_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_event_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at            DATETIME NULL,
    duration_seconds    INT NOT NULL DEFAULT 0,
    events_count        INT NOT NULL DEFAULT 0,
    ip_address          VARCHAR(45) NOT NULL DEFAULT '',
    country_code        CHAR(2)      NULL,
    country             VARCHAR(100) NULL,
    region              VARCHAR(100) NULL,
    city                VARCHAR(100) NULL,
    latitude            DECIMAL(10, 6) NULL,
    longitude           DECIMAL(10, 6) NULL,
    timezone            VARCHAR(60)  NULL,
    user_agent          VARCHAR(500) NULL,
    INDEX idx_sessions_user (user_id, started_at),
    INDEX idx_sessions_country (country_code, started_at),
    INDEX idx_sessions_started (started_at),
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Events: high-volume row store for every tracked behavior. Course/lesson
--    are denormalised onto the event so the lesson funnel doesn't need joins.
CREATE TABLE IF NOT EXISTS user_events (
    id                  BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id             VARCHAR(64) NOT NULL,
    session_id          VARCHAR(64) NULL,
    device_pk           VARCHAR(64) NULL,
    event_name          VARCHAR(80) NOT NULL,
    screen              VARCHAR(80) NULL,
    course_id           VARCHAR(64) NULL,
    lesson_id           VARCHAR(64) NULL,
    value_numeric       DECIMAL(15, 4) NULL,   -- e.g. duration, %, price
    props_json          TEXT NULL,
    occurred_at         DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    received_at         DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    INDEX idx_events_user_time (user_id, occurred_at),
    INDEX idx_events_name_time (event_name, occurred_at),
    INDEX idx_events_lesson (lesson_id, occurred_at),
    INDEX idx_events_screen (screen, occurred_at),
    INDEX idx_events_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) Login audit log: separate from events so security review stays fast
--    even when behavior events grow into the millions.
CREATE TABLE IF NOT EXISTS user_login_history (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id         VARCHAR(64) NULL,           -- NULL when login attempt failed before user lookup
    email_attempted VARCHAR(190) NULL,
    method          ENUM('PASSWORD','API_TOKEN','SESSION','SSO') NOT NULL DEFAULT 'PASSWORD',
    surface         ENUM('ADMIN','API','WEB') NOT NULL DEFAULT 'API',
    ip_address      VARCHAR(45) NOT NULL DEFAULT '',
    country_code    CHAR(2)      NULL,
    country         VARCHAR(100) NULL,
    city            VARCHAR(100) NULL,
    user_agent      VARCHAR(500) NULL,
    success         TINYINT(1) NOT NULL DEFAULT 1,
    failure_reason  VARCHAR(100) NULL,
    attempted_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_user (user_id, attempted_at),
    INDEX idx_login_ip (ip_address, attempted_at),
    INDEX idx_login_failed (success, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6) Daily aggregates: rebuilt nightly by analytics:rebuild cron / CLI. The
--    admin dashboard reads from here, never from raw events, so it stays
--    snappy even at 100M+ events.
CREATE TABLE IF NOT EXISTS analytics_daily (
    `date`              DATE PRIMARY KEY,
    dau                 INT NOT NULL DEFAULT 0,
    new_users           INT NOT NULL DEFAULT 0,
    returning_users     INT NOT NULL DEFAULT 0,
    sessions_count      INT NOT NULL DEFAULT 0,
    events_count        INT NOT NULL DEFAULT 0,
    avg_session_seconds INT NOT NULL DEFAULT 0,
    revenue_cents       BIGINT NOT NULL DEFAULT 0,
    paying_users        INT NOT NULL DEFAULT 0,
    lessons_started     INT NOT NULL DEFAULT 0,
    lessons_completed   INT NOT NULL DEFAULT 0,
    qa_posted           INT NOT NULL DEFAULT 0,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7) Per-country aggregate (rolling, dimensioned by date for trends).
CREATE TABLE IF NOT EXISTS analytics_geography (
    country_code    CHAR(2)      NOT NULL,
    country         VARCHAR(100) NOT NULL,
    `date`          DATE NOT NULL,
    users_count     INT NOT NULL DEFAULT 0,
    sessions_count  INT NOT NULL DEFAULT 0,
    revenue_cents   BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY (country_code, `date`),
    INDEX idx_geo_date (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8) Per-platform aggregate.
CREATE TABLE IF NOT EXISTS analytics_devices (
    platform        ENUM('ANDROID','IOS','WEB','OTHER') NOT NULL,
    `date`          DATE NOT NULL,
    users_count     INT NOT NULL DEFAULT 0,
    sessions_count  INT NOT NULL DEFAULT 0,
    PRIMARY KEY (platform, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
