<?php
/**
 * Idempotent seeder. Safe to re-run on production — INSERT IGNORE means
 * existing rows aren't overwritten and you don't lose data. Adds:
 *
 *  - 1 admin user from ADMIN_* in .env (so you can log into /admin)
 *  - 6 sample courses + lessons mirroring the Android app's SeedData
 *  - 3 demo coupons (WELCOME20 / LAUNCH50 / STUDENT10)
 *
 * Run: `php seeds/seed.php`
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Devithor\Auth;
use Devithor\Database;

function uuid(): string
{
    return bin2hex(random_bytes(8)); // 16-char short id, plenty for course/lesson scale
}

// ---- Admin bootstrap ----------------------------------------------------
$adminEmail = getenv('ADMIN_EMAIL') ?: 'admin@devithor.com';
$adminName  = getenv('ADMIN_NAME')  ?: 'Site Admin';
$adminPass  = getenv('ADMIN_PASSWORD') ?: '';

if ($adminPass === '' || $adminPass === 'change-me-now-then-remove-from-env') {
    fwrite(STDERR, "Refusing to seed admin: set ADMIN_PASSWORD to a real value in .env first.\n");
    exit(1);
}

$existing = Database::one('SELECT id FROM users WHERE email = ?', [$adminEmail]);
if ($existing) {
    echo "Admin already exists ({$adminEmail}) — skipping.\n";
} else {
    $id = 'u_' . uuid();
    Database::exec(
        'INSERT INTO users (id, email, full_name, role, password_hash) VALUES (?, ?, ?, ?, ?)',
        [$id, $adminEmail, $adminName, 'ADMIN', Auth::hashPassword($adminPass)],
    );
    echo "Created admin: $adminEmail / id=$id\n";
}

// ---- Coupons ------------------------------------------------------------
$coupons = [
    ['WELCOME20', '20% off your first month', 20, null],
    ['LAUNCH50',  '50% off first 3 months',   50, null],
    ['STUDENT10', '$10 off any annual plan',  null, 1000],
];
foreach ($coupons as [$code, $desc, $pct, $cents]) {
    Database::exec(
        'INSERT IGNORE INTO coupons (code, description, discount_percent, discount_cents)
         VALUES (?, ?, ?, ?)',
        [$code, $desc, $pct, $cents],
    );
}
echo "Seeded coupons.\n";

// ---- Courses ------------------------------------------------------------
$courses = [
    ['c_kotlin_101', 'Kotlin for Android: From Zero to Production',
        'Build apps the modern way — coroutines, Compose, Clean Architecture',
        'A complete on-ramp to professional Android development.',
        'Anitha R.', '#7C5CFF', 'Mobile', 'BEGINNER', 6, 240, 4.9, 1240, 0],
    ['c_compose_ui', 'Jetpack Compose: Adaptive UI Mastery',
        'Phones, tablets, foldables — one codebase',
        'Master WindowSizeClass, motion, theming, and accessibility.',
        'Vikram S.', '#22D3EE', 'Mobile', 'INTERMEDIATE', 5, 180, 4.8, 870, 0],
    ['c_system_design', 'Distributed Systems for Backend Engineers',
        'From single server to global scale',
        'Sharding, replication, consensus, and the real tradeoffs.',
        'Priya M.', '#F59E0B', 'Backend', 'ADVANCED', 4, 320, 4.95, 2100, 1],
    ['c_ai_engineering', 'Building with Claude: Production AI Applications',
        'Tool use, prompt caching, evaluations',
        'Ship robust LLM features — RAG, agents, observability.',
        'Rohan K.', '#10B981', 'AI', 'INTERMEDIATE', 5, 200, 4.85, 540, 1],
    ['c_dsa_interview', 'Data Structures & Algorithms: Interview Crash Course',
        'Patterns that crack any FAANG interview',
        '75 problems organized by pattern with intuition-first explanations.',
        'Meera L.', '#EF4444', 'CS Fundamentals', 'INTERMEDIATE', 6, 360, 4.75, 3300, 0],
    ['c_product_design', 'Product Design for Engineers',
        'Think like a designer, ship like an engineer',
        'Information architecture, type systems, design tokens.',
        'Kavya N.', '#EC4899', 'Design', 'BEGINNER', 4, 160, 4.6, 410, 0],
];
foreach ($courses as $c) {
    Database::exec(
        'INSERT IGNORE INTO courses
         (id, title, subtitle, description, instructor_name, cover_color_hex,
          category, difficulty, total_lessons, duration_minutes, rating, rating_count, is_premium)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        $c,
    );
}
echo "Seeded " . count($courses) . " courses.\n";

// ---- Lessons ------------------------------------------------------------
$sampleVideo = 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4';
$lessonTitles = [
    'c_kotlin_101' => [
        'Why Kotlin won Android', 'Coroutines and structured concurrency',
        'Flows for reactive state', 'Clean Architecture in practice',
        'Compose: your first screen', 'Shipping to the Play Store',
    ],
    'c_compose_ui' => [
        'WindowSizeClass demystified', 'Adaptive layouts: list-detail',
        'Material3 theming + dynamic color', 'Motion that feels physical',
        'Accessibility & TalkBack',
    ],
    'c_system_design' => [
        'Why distributed systems are hard', 'Replication and consistency models',
        'Consensus: Raft, intuitively', 'Designing for global scale',
    ],
    'c_ai_engineering' => [
        'Anatomy of an LLM call', 'Prompt caching: 90% cost savings',
        'Tool use done right', 'Evals before features',
        'Observability and cost control',
    ],
    'c_dsa_interview' => [
        'Two pointers', 'Sliding window', 'Binary search variations',
        'Graphs: BFS/DFS templates', 'Dynamic programming patterns',
        'System design lite for SWE-2',
    ],
    'c_product_design' => [
        'What makes a UI feel premium', 'Type scale and hierarchy',
        'Color systems that scale', 'Running a useful design review',
    ],
];
foreach ($lessonTitles as $courseId => $titles) {
    foreach ($titles as $i => $title) {
        $lessonId = $courseId . '_l' . ($i + 1);
        Database::exec(
            'INSERT IGNORE INTO lessons
             (id, course_id, title, order_index, duration_seconds, video_url, description, is_free_preview)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $lessonId, $courseId, $title, $i,
                600 + ($i * 90), $sampleVideo,
                "In this lesson: $title.",
                $i === 0 ? 1 : 0,
            ],
        );
    }
}
echo "Seeded lessons.\n";
echo "\nAll done. Sign in at /admin/login as $adminEmail.\n";
