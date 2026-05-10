-- Advanced video management: provider metadata on lessons + per-play
-- analytics + Cloudflare/anti-piracy settings.
--
-- Strategy: keep video bytes outside the LMS. Admins paste a YouTube
-- (unlisted), Vimeo, HLS .m3u8, MP4, or Cloudflare Stream URL; the helper
-- in src/Video.php parses the provider and embeds it. Free YouTube
-- bandwidth + a Cloudflare front-end on apptesting.in is enough for
-- ~10k concurrent learners before paid Stream becomes interesting.

-- 1) Per-lesson video metadata.
ALTER TABLE lessons
    ADD COLUMN video_provider     ENUM('YOUTUBE','VIMEO','HLS','MP4','CLOUDFLARE','OTHER')
                                  NOT NULL DEFAULT 'OTHER' AFTER video_url,
    ADD COLUMN video_id           VARCHAR(120) NOT NULL DEFAULT '' AFTER video_provider,
    ADD COLUMN thumbnail_url      VARCHAR(500) NULL          AFTER video_id,
    ADD COLUMN subtitles_url      VARCHAR(500) NULL          AFTER thumbnail_url,
    ADD COLUMN chapters_json      TEXT         NULL          AFTER subtitles_url,
    ADD COLUMN is_downloadable    TINYINT(1)   NOT NULL DEFAULT 0 AFTER chapters_json,
    ADD COLUMN allow_speed        TINYINT(1)   NOT NULL DEFAULT 1 AFTER is_downloadable,
    ADD COLUMN watermark_enabled  TINYINT(1)   NOT NULL DEFAULT 0 AFTER allow_speed,
    ADD INDEX idx_lessons_provider (video_provider);

-- 2) Per-play analytics. Each row = one playback session of a lesson.
--    progress_pct lets us draw drop-off heatmaps; replay_count picks up
--    the segments that get watched twice.
CREATE TABLE IF NOT EXISTS video_views (
    id                  BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id             VARCHAR(64) NOT NULL,
    lesson_id           VARCHAR(64) NOT NULL,
    course_id           VARCHAR(64) NOT NULL,
    session_id          VARCHAR(64) NULL,
    started_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at            DATETIME NULL,
    watch_seconds       INT      NOT NULL DEFAULT 0,
    progress_pct        TINYINT  NOT NULL DEFAULT 0,
    completed           TINYINT(1) NOT NULL DEFAULT 0,
    speed               DECIMAL(3,1) NOT NULL DEFAULT 1.0,
    quality             VARCHAR(20)  NULL,
    INDEX idx_views_lesson (lesson_id, started_at),
    INDEX idx_views_user   (user_id, started_at),
    INDEX idx_views_course (course_id, started_at),
    CONSTRAINT fk_views_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    CONSTRAINT fk_views_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Per-segment watch counter. Bucket the lesson into 20 chunks (5% each)
--    and increment each chunk on every playback that touches it. The
--    drop-off chart is `MAX(views) - views[i]` per chunk.
CREATE TABLE IF NOT EXISTS video_segments (
    lesson_id   VARCHAR(64) NOT NULL,
    bucket      TINYINT     NOT NULL,
    views       INT         NOT NULL DEFAULT 0,
    PRIMARY KEY (lesson_id, bucket),
    CONSTRAINT fk_segments_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Cloudflare / anti-piracy / default-provider settings live in app_settings.
INSERT IGNORE INTO app_settings (`key`, `value`, value_type, `group`, label, description, is_secret, sort_order) VALUES
    ('video_default_provider', 'YOUTUBE', 'STRING', 'video', 'Default provider', 'Used when admin pastes a URL the parser can''t classify.', 0, 10),
    ('video_youtube_no_cookie','1',       'BOOL',   'video', 'YouTube no-cookie mode', 'Embed via youtube-nocookie.com (privacy + GDPR).', 0, 20),
    ('video_disable_download','1',        'BOOL',   'video', 'Disable download', 'Adds controlsList=nodownload + disablePictureInPicture on HTML5 players.', 0, 30),
    ('video_disable_speed',  '0',         'BOOL',   'video', 'Hide speed control', 'Hide the playback-rate menu (per-lesson override possible).', 0, 40),
    ('video_show_watermark', '0',         'BOOL',   'video', 'User-email watermark', 'Overlay learner email on the player so leaks are traceable.', 0, 50),
    ('video_max_quality',    '1080p',     'STRING', 'video', 'Max quality', 'Cap streamed quality. Useful in regions with metered bandwidth.', 0, 60),
    ('cf_account_id',        '',          'STRING', 'video', 'Cloudflare account id', 'For Stream API. Required only if using Cloudflare Stream.', 0, 110),
    ('cf_stream_token',      '',          'STRING', 'video', 'Cloudflare Stream API token', 'Leave blank if you only use YouTube/HLS/MP4.', 1, 120),
    ('cf_stream_signing_key','',          'STRING', 'video', 'Stream signing key', 'For time-limited signed playback URLs.', 1, 130);
