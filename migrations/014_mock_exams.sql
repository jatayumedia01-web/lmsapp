-- Mock Exam system — class/subject targeted exams with timer, auto-results, certificates

CREATE TABLE IF NOT EXISTS mock_exams (
    id                  VARCHAR(64)  PRIMARY KEY,
    title               VARCHAR(255) NOT NULL,
    description         TEXT         NULL,
    class_id            VARCHAR(64)  NULL,
    subject_tag         VARCHAR(100) NULL,
    duration_minutes    INT          NOT NULL DEFAULT 60,
    total_marks         INT          NOT NULL DEFAULT 100,
    pass_marks          INT          NOT NULL DEFAULT 40,
    rules_text          TEXT         NULL,
    plan_required       ENUM('FREE','BASIC','PREMIUM') NULL COMMENT 'NULL = all plans',
    is_published        TINYINT(1)   NOT NULL DEFAULT 0,
    shuffle_questions   TINYINT(1)   NOT NULL DEFAULT 1,
    show_answers_after  TINYINT(1)   NOT NULL DEFAULT 1,
    max_attempts        INT          NOT NULL DEFAULT 1,
    scheduled_at        DATETIME     NULL,
    expires_at          DATETIME     NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_exams_class (class_id),
    INDEX idx_exams_published (is_published)
);

CREATE TABLE IF NOT EXISTS exam_questions (
    id              VARCHAR(64)  PRIMARY KEY,
    exam_id         VARCHAR(64)  NOT NULL,
    question_text   TEXT         NOT NULL,
    option_a        VARCHAR(500) NOT NULL,
    option_b        VARCHAR(500) NOT NULL,
    option_c        VARCHAR(500) NULL,
    option_d        VARCHAR(500) NULL,
    correct_option  CHAR(1)      NOT NULL COMMENT 'A B C or D',
    marks           INT          NOT NULL DEFAULT 1,
    explanation     TEXT         NULL,
    order_index     INT          NOT NULL DEFAULT 0,
    INDEX idx_eq_exam (exam_id)
);

CREATE TABLE IF NOT EXISTS exam_attempts (
    id                      VARCHAR(64)  PRIMARY KEY,
    exam_id                 VARCHAR(64)  NOT NULL,
    user_id                 VARCHAR(64)  NOT NULL,
    started_at              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at            DATETIME     NULL,
    time_taken_seconds      INT          NULL,
    score                   INT          NULL,
    total_marks             INT          NOT NULL,
    pass_marks              INT          NOT NULL,
    passed                  TINYINT(1)   NULL,
    status                  ENUM('IN_PROGRESS','SUBMITTED','TIMED_OUT') NOT NULL DEFAULT 'IN_PROGRESS',
    certificate_number      VARCHAR(64)  NULL UNIQUE,
    certificate_issued_at   DATETIME     NULL,
    INDEX idx_ea_exam (exam_id),
    INDEX idx_ea_user (user_id),
    INDEX idx_ea_user_exam (user_id, exam_id)
);

CREATE TABLE IF NOT EXISTS exam_answers (
    id              BIGINT       AUTO_INCREMENT PRIMARY KEY,
    attempt_id      VARCHAR(64)  NOT NULL,
    question_id     VARCHAR(64)  NOT NULL,
    selected_option CHAR(1)      NULL,
    is_correct      TINYINT(1)   NULL,
    marks_awarded   INT          NULL DEFAULT 0,
    INDEX idx_ans_attempt (attempt_id)
);
