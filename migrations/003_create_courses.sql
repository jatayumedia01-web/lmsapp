-- Courses + lessons. Mirrors the Android Course / Lesson domain models field
-- for field — the API ships this shape directly.

CREATE TABLE IF NOT EXISTS courses (
    id                  VARCHAR(64) PRIMARY KEY,
    title               VARCHAR(190) NOT NULL,
    subtitle            VARCHAR(255) NOT NULL DEFAULT '',
    description         TEXT NOT NULL,
    instructor_name     VARCHAR(190) NOT NULL,
    cover_color_hex     VARCHAR(9) NOT NULL DEFAULT '#7C5CFF',
    cover_image_url     VARCHAR(500) NULL,
    category            VARCHAR(60) NOT NULL,
    difficulty          ENUM('BEGINNER', 'INTERMEDIATE', 'ADVANCED') NOT NULL DEFAULT 'BEGINNER',
    total_lessons       INT NOT NULL DEFAULT 0,
    duration_minutes    INT NOT NULL DEFAULT 0,
    rating              DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    rating_count        INT NOT NULL DEFAULT 0,
    is_premium          TINYINT(1) NOT NULL DEFAULT 0,
    is_published        TINYINT(1) NOT NULL DEFAULT 1,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_courses_category (category),
    INDEX idx_courses_difficulty (difficulty),
    INDEX idx_courses_published (is_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lessons (
    id                  VARCHAR(64) PRIMARY KEY,
    course_id           VARCHAR(64) NOT NULL,
    title               VARCHAR(255) NOT NULL,
    order_index         INT NOT NULL DEFAULT 0,
    duration_seconds    INT NOT NULL DEFAULT 0,
    video_url           VARCHAR(500) NOT NULL,
    description         TEXT NOT NULL,
    is_free_preview     TINYINT(1) NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_lessons_course (course_id, order_index),
    CONSTRAINT fk_lessons_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
