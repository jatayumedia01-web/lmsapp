-- Class → Subject (course) → Lesson → FAQ hierarchy.
--
-- Existing data preserved:
--  * `courses` table is reused as the "Subject" tier — we just add a
--    nullable class_id FK so older rows continue to work uncategorised.
--  * Lessons stay attached to courses; nothing changes there.
--
-- New tables: `classes` (top-level group) and `lesson_faqs` (per-lesson
-- frequently-asked questions managed from the admin UI).

CREATE TABLE IF NOT EXISTS classes (
    id              VARCHAR(64) PRIMARY KEY,
    name            VARCHAR(190) NOT NULL,
    slug            VARCHAR(120) NOT NULL UNIQUE,
    description     TEXT         NOT NULL,
    level           VARCHAR(60)  NOT NULL DEFAULT '',     -- "Class 10", "NEET", "JEE Mains"
    cover_image_url VARCHAR(500) NULL,
    cover_color_hex VARCHAR(9)   NOT NULL DEFAULT '#7C5CFF',
    sort_order      INT          NOT NULL DEFAULT 0,
    is_published    TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_classes_published (is_published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE courses
    ADD COLUMN class_id VARCHAR(64) NULL AFTER id,
    ADD INDEX idx_courses_class (class_id),
    ADD CONSTRAINT fk_courses_class FOREIGN KEY (class_id)
        REFERENCES classes(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS lesson_faqs (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    lesson_id       VARCHAR(64) NOT NULL,
    question        VARCHAR(500) NOT NULL,
    answer          TEXT NOT NULL,
    order_index     INT NOT NULL DEFAULT 0,
    is_published    TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_faqs_lesson (lesson_id, order_index),
    CONSTRAINT fk_faqs_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings: where direct uploads live + which providers are enabled by default.
INSERT IGNORE INTO app_settings (`key`, `value`, value_type, `group`, label, description, is_secret, sort_order) VALUES
    ('video_upload_max_mb',      '256',                                  'INT',    'video', 'Direct upload max size (MB)', 'Hard cap on uploaded video size. Hostinger shared plans allow up to ~256MB by default.', 0, 70),
    ('video_upload_directory',   'uploads/videos',                       'STRING', 'video', 'Direct upload folder',        'Relative to /public. Files served as static assets.',                                       0, 80),
    ('video_providers_enabled',  'YOUTUBE,VIMEO,HLS,MP4,UPLOAD,CLOUDFLARE','STRING', 'video', 'Enabled providers',          'Comma-separated list controls which tabs show in the lesson editor.',                       0, 90);
