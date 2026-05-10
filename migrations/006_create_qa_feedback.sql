-- Lesson Q&A + per-lesson written feedback + a unified votes table for both
-- questions and answers (matching the Android domain).

CREATE TABLE IF NOT EXISTS lesson_questions (
    id              VARCHAR(64) PRIMARY KEY,
    lesson_id       VARCHAR(64) NOT NULL,
    course_id       VARCHAR(64) NOT NULL,
    author_id       VARCHAR(64) NOT NULL,
    author_name     VARCHAR(190) NOT NULL,
    body            TEXT NOT NULL,
    like_count      INT NOT NULL DEFAULT 0,
    dislike_count   INT NOT NULL DEFAULT 0,
    answer_count    INT NOT NULL DEFAULT 0,
    is_resolved     TINYINT(1) NOT NULL DEFAULT 0,
    is_pinned       TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_questions_lesson (lesson_id, is_pinned, like_count),
    CONSTRAINT fk_questions_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    CONSTRAINT fk_questions_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_answers (
    id              VARCHAR(64) PRIMARY KEY,
    question_id     VARCHAR(64) NOT NULL,
    author_id       VARCHAR(64) NOT NULL,
    author_name     VARCHAR(190) NOT NULL,
    body            TEXT NOT NULL,
    like_count      INT NOT NULL DEFAULT 0,
    dislike_count   INT NOT NULL DEFAULT 0,
    is_instructor   TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_answers_question (question_id, is_instructor, like_count),
    CONSTRAINT fk_answers_question FOREIGN KEY (question_id) REFERENCES lesson_questions(id) ON DELETE CASCADE,
    CONSTRAINT fk_answers_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_feedback (
    user_id         VARCHAR(64) NOT NULL,
    lesson_id       VARCHAR(64) NOT NULL,
    helpful         TINYINT(1) NULL,
    comment         TEXT NULL,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, lesson_id),
    INDEX idx_feedback_lesson (lesson_id),
    CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_feedback_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS votes (
    user_id         VARCHAR(64) NOT NULL,
    target_id       VARCHAR(64) NOT NULL,
    target_type     ENUM('QUESTION', 'ANSWER') NOT NULL,
    value           ENUM('UP', 'DOWN') NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, target_id),
    INDEX idx_votes_target (target_type, target_id),
    CONSTRAINT fk_votes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
