-- Five advanced LMS pillars: quizzes, certificates, notes, notifications,
-- and the user_preferences row that gates email/push.
--
-- Each table owns its own write path; nothing here joins across pillars
-- except via user_id — that keeps DELETEs cascading cleanly.

-- =============== 1) Quizzes ===============
CREATE TABLE IF NOT EXISTS quizzes (
    id                   VARCHAR(64) PRIMARY KEY,
    scope                ENUM('LESSON','SUBJECT','CLASS') NOT NULL DEFAULT 'LESSON',
    parent_id            VARCHAR(64) NOT NULL,
    title                VARCHAR(255) NOT NULL,
    description          TEXT NULL,
    instructions         TEXT NULL,
    pass_score_pct       INT NOT NULL DEFAULT 70,
    time_limit_minutes   INT NOT NULL DEFAULT 0,
    max_attempts         INT NOT NULL DEFAULT 0,
    shuffle_questions    TINYINT(1) NOT NULL DEFAULT 1,
    shuffle_options      TINYINT(1) NOT NULL DEFAULT 1,
    show_correct_answers TINYINT(1) NOT NULL DEFAULT 1,
    is_published         TINYINT(1) NOT NULL DEFAULT 0,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_quizzes_parent (scope, parent_id),
    INDEX idx_quizzes_published (is_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_questions (
    id                  BIGINT AUTO_INCREMENT PRIMARY KEY,
    quiz_id             VARCHAR(64) NOT NULL,
    question_type       ENUM('MCQ','MULTI','TRUE_FALSE','SHORT','FILL') NOT NULL DEFAULT 'MCQ',
    question_text       TEXT NOT NULL,
    explanation         TEXT NULL,
    image_url           VARCHAR(500) NULL,
    points              INT NOT NULL DEFAULT 1,
    order_index         INT NOT NULL DEFAULT 0,
    options_json        TEXT NULL,
    correct_answer_text VARCHAR(500) NULL,
    is_required         TINYINT(1) NOT NULL DEFAULT 1,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_questions_quiz (quiz_id, order_index),
    CONSTRAINT fk_questions_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_attempts (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    quiz_id         VARCHAR(64) NOT NULL,
    user_id         VARCHAR(64) NOT NULL,
    started_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at    DATETIME NULL,
    score_pct       DECIMAL(5,2) NULL,
    points_earned   INT NOT NULL DEFAULT 0,
    points_total    INT NOT NULL DEFAULT 0,
    status          ENUM('IN_PROGRESS','SUBMITTED','EXPIRED') NOT NULL DEFAULT 'IN_PROGRESS',
    passed          TINYINT(1) NOT NULL DEFAULT 0,
    duration_seconds INT NOT NULL DEFAULT 0,
    INDEX idx_attempts_quiz_user (quiz_id, user_id, started_at),
    INDEX idx_attempts_user (user_id, started_at),
    CONSTRAINT fk_attempts_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    CONSTRAINT fk_attempts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_answers (
    id                    BIGINT AUTO_INCREMENT PRIMARY KEY,
    attempt_id            BIGINT NOT NULL,
    question_id           BIGINT NOT NULL,
    answer_text           TEXT NULL,
    selected_options_json TEXT NULL,
    is_correct            TINYINT(1) NOT NULL DEFAULT 0,
    points_earned         INT NOT NULL DEFAULT 0,
    answered_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_answers_attempt (attempt_id),
    CONSTRAINT fk_answers_attempt  FOREIGN KEY (attempt_id)  REFERENCES quiz_attempts(id)  ON DELETE CASCADE,
    CONSTRAINT fk_answers_question FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============== 2) Certificates ===============
CREATE TABLE IF NOT EXISTS certificate_templates (
    id              VARCHAR(64) PRIMARY KEY,
    name            VARCHAR(190) NOT NULL,
    description     TEXT NULL,
    html_template   TEXT NOT NULL,
    css             TEXT NULL,
    is_default      TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS certificates (
    id                    VARCHAR(64) PRIMARY KEY,
    certificate_number    VARCHAR(50) NOT NULL UNIQUE,
    user_id               VARCHAR(64) NOT NULL,
    course_id             VARCHAR(64) NULL,
    class_id              VARCHAR(64) NULL,
    template_id           VARCHAR(64) NULL,
    user_name_snapshot    VARCHAR(190) NOT NULL,
    course_title_snapshot VARCHAR(190) NOT NULL DEFAULT '',
    score_pct             DECIMAL(5,2) NULL,
    issued_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at            DATETIME NULL,
    INDEX idx_certs_user (user_id, issued_at),
    INDEX idx_certs_number (certificate_number),
    CONSTRAINT fk_certs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed one default template — admins can edit or duplicate.
INSERT IGNORE INTO certificate_templates (id, name, description, html_template, css, is_default) VALUES
    ('tpl_default', 'Default certificate', 'Clean centered layout with gold accent.',
'<div class="cert">
  <div class="cert-border"></div>
  <h1>Certificate of Completion</h1>
  <p class="cert-intro">This is to certify that</p>
  <h2 class="cert-name">{{user_name}}</h2>
  <p class="cert-body">has successfully completed</p>
  <h3 class="cert-course">{{course_title}}</h3>
  <p class="cert-meta">Issued on {{issued_date}}</p>
  <p class="cert-meta">Certificate # {{certificate_number}}</p>
  <p class="cert-verify">Verify at apptesting.in/verify/{{certificate_number}}</p>
</div>',
'.cert { width: 800px; height: 560px; padding: 60px; text-align: center; background: #fff; color: #1a1a1a; border: 8px double #c9a961; font-family: Georgia, serif; position: relative; }
.cert h1 { font-size: 36px; margin: 0 0 30px; color: #c9a961; letter-spacing: 2px; text-transform: uppercase; }
.cert-intro { font-size: 14px; color: #666; margin: 0; }
.cert-name { font-size: 38px; margin: 12px 0; font-style: italic; color: #2c2c2c; border-bottom: 2px solid #c9a961; padding-bottom: 12px; display: inline-block; }
.cert-body { font-size: 14px; color: #666; margin: 24px 0 8px; }
.cert-course { font-size: 24px; margin: 8px 0 36px; color: #1a1a1a; }
.cert-meta { font-size: 12px; color: #999; margin: 4px 0; }
.cert-verify { font-size: 11px; color: #c9a961; margin-top: 28px; font-family: monospace; }',
1);

-- =============== 3) User notes ===============
CREATE TABLE IF NOT EXISTS user_notes (
    id                BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id           VARCHAR(64) NOT NULL,
    lesson_id         VARCHAR(64) NOT NULL,
    course_id         VARCHAR(64) NOT NULL,
    note_text         TEXT NOT NULL,
    timestamp_seconds INT NULL,
    color             VARCHAR(20) NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_notes_user_lesson (user_id, lesson_id),
    INDEX idx_notes_lesson (lesson_id, timestamp_seconds),
    CONSTRAINT fk_notes_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    CONSTRAINT fk_notes_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============== 4) Notifications ===============
CREATE TABLE IF NOT EXISTS notifications (
    id          BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id     VARCHAR(64) NOT NULL,
    type        VARCHAR(50) NOT NULL DEFAULT 'INFO',
    title       VARCHAR(255) NOT NULL,
    body        TEXT NOT NULL,
    link        VARCHAR(500) NULL,
    icon        VARCHAR(20) NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    sent_email  TINYINT(1) NOT NULL DEFAULT 0,
    sent_push   TINYINT(1) NOT NULL DEFAULT 0,
    campaign_id BIGINT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at     DATETIME NULL,
    INDEX idx_notif_user_unread (user_id, is_read, created_at),
    INDEX idx_notif_campaign (campaign_id),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_campaigns (
    id            BIGINT AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(255) NOT NULL,
    body          TEXT NOT NULL,
    link          VARCHAR(500) NULL,
    icon          VARCHAR(20) NULL,
    target        ENUM('ALL','CLASS','SUBJECT','PAYING','BANNED','ROLE') NOT NULL DEFAULT 'ALL',
    target_id     VARCHAR(64) NULL,
    send_email    TINYINT(1) NOT NULL DEFAULT 0,
    send_push     TINYINT(1) NOT NULL DEFAULT 1,
    sent_count    INT NOT NULL DEFAULT 0,
    sent_at       DATETIME NULL,
    scheduled_for DATETIME NULL,
    created_by    VARCHAR(64) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_campaigns_sent (sent_at),
    INDEX idx_campaigns_scheduled (scheduled_for)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============== 5) User preferences ===============
CREATE TABLE IF NOT EXISTS user_preferences (
    user_id              VARCHAR(64) PRIMARY KEY,
    email_notifications  TINYINT(1) NOT NULL DEFAULT 1,
    push_notifications   TINYINT(1) NOT NULL DEFAULT 1,
    weekly_digest        TINYINT(1) NOT NULL DEFAULT 1,
    timezone             VARCHAR(60) NOT NULL DEFAULT 'UTC',
    language             VARCHAR(20) NOT NULL DEFAULT 'en',
    daily_goal_minutes   INT NOT NULL DEFAULT 30,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_prefs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
