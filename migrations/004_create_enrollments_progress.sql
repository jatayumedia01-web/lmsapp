-- Enrollments + lesson progress + bookmarks. The two latter tables use
-- composite PKs to enforce "one row per (user, target)" at the schema layer.

CREATE TABLE IF NOT EXISTS enrollments (
    id                          VARCHAR(64) PRIMARY KEY,
    user_id                     VARCHAR(64) NOT NULL,
    course_id                   VARCHAR(64) NOT NULL,
    enrolled_at                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_accessed_lesson_id     VARCHAR(64) NULL,
    UNIQUE KEY uniq_enrollment (user_id, course_id),
    INDEX idx_enrollments_user (user_id),
    CONSTRAINT fk_enrollments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_enrollments_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_progress (
    user_id         VARCHAR(64) NOT NULL,
    lesson_id       VARCHAR(64) NOT NULL,
    course_id       VARCHAR(64) NOT NULL,
    completed       TINYINT(1) NOT NULL DEFAULT 0,
    watched_seconds INT NOT NULL DEFAULT 0,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, lesson_id),
    INDEX idx_progress_user_course (user_id, course_id),
    CONSTRAINT fk_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_progress_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookmarks (
    user_id     VARCHAR(64) NOT NULL,
    course_id   VARCHAR(64) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, course_id),
    CONSTRAINT fk_bookmarks_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_bookmarks_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
