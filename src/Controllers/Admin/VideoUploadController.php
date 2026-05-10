<?php
declare(strict_types=1);

namespace Devithor\Controllers\Admin;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;

/**
 * Direct video upload — POST /admin/api/upload-video.
 *
 * Stores files under /public/{video_upload_directory}/ (default: uploads/videos)
 * with a flat layout: {lesson_id}/{timestamp}_{slug}.{ext}. Returns the public
 * URL the lesson editor should save into video_url.
 *
 * Security:
 *   - Only allow whitelisted MIME types + file extensions (mp4, webm, mov).
 *   - Size cap from app_settings.video_upload_max_mb (default 256).
 *   - The upload folder ships with a hardened .htaccess (PHP execution off,
 *     dotfiles blocked) — see public/uploads/videos/.htaccess.
 */
final class VideoUploadController
{
    private const ALLOWED_EXT  = ['mp4', 'webm', 'mov', 'm4v'];
    private const ALLOWED_MIME = [
        'video/mp4', 'video/webm', 'video/quicktime', 'video/x-m4v',
    ];

    public function upload(Request $req): never
    {
        if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $err = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
            Response::json(['error' => $this->describeUploadError((int) $err)], 400);
        }
        $file = $_FILES['file'];

        $maxMb = (int) (Database::scalar('SELECT `value` FROM app_settings WHERE `key` = ?', ['video_upload_max_mb']) ?? 256);
        if ((int) $file['size'] > $maxMb * 1024 * 1024) {
            Response::json(['error' => 'File too large (max ' . $maxMb . ' MB).'], 400);
        }

        $ext = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            Response::json(['error' => 'Unsupported file type. Allowed: ' . implode(', ', self::ALLOWED_EXT)], 400);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = (string) $finfo->file((string) $file['tmp_name']);
        if (!in_array($detectedMime, self::ALLOWED_MIME, true)) {
            Response::json(['error' => 'File contents do not look like a video (' . $detectedMime . ').'], 400);
        }

        $lessonId = trim((string) ($req->input('lesson_id') ?? ''));
        if ($lessonId === '') {
            Response::json(['error' => 'lesson_id required.'], 400);
        }

        $relRoot = trim((string) (Database::scalar('SELECT `value` FROM app_settings WHERE `key` = ?', ['video_upload_directory']) ?? 'uploads/videos'), '/');
        $publicRoot = realpath(__DIR__ . '/../../../public') ?: (__DIR__ . '/../../../public');
        $absDir = $publicRoot . '/' . $relRoot . '/' . $this->safeSegment($lessonId);

        if (!is_dir($absDir)) {
            if (!@mkdir($absDir, 0755, true) && !is_dir($absDir)) {
                Response::json(['error' => 'Could not create upload directory.'], 500);
            }
            // Drop a hardened .htaccess in the parent root once, so PHP execution
            // is disabled even if someone uploads a .php-named file.
            $parentHtaccess = $publicRoot . '/' . $relRoot . '/.htaccess';
            if (!file_exists($parentHtaccess)) {
                @file_put_contents($parentHtaccess, $this->uploadHtaccess());
            }
        }

        $base = $this->safeSegment(pathinfo((string) $file['name'], PATHINFO_FILENAME)) ?: 'video';
        $name = date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '_' . $base . '.' . $ext;
        $destAbs = $absDir . '/' . $name;

        if (!@move_uploaded_file((string) $file['tmp_name'], $destAbs)) {
            Response::json(['error' => 'Failed to save uploaded file.'], 500);
        }
        @chmod($destAbs, 0644);

        $publicUrl = '/' . $relRoot . '/' . $this->safeSegment($lessonId) . '/' . $name;
        Response::json([
            'ok'       => true,
            'url'      => $publicUrl,
            'size'     => (int) $file['size'],
            'mime'     => $detectedMime,
            'provider' => 'MP4',
        ]);
    }

    private function safeSegment(string $segment): string
    {
        $segment = strtolower($segment);
        $segment = preg_replace('~[^a-z0-9_\-]+~', '-', $segment) ?? '';
        return trim($segment, '-') ?: 'item';
    }

    private function describeUploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload_max_filesize limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form size limit.',
            UPLOAD_ERR_PARTIAL    => 'Upload was interrupted; try again.',
            UPLOAD_ERR_NO_FILE    => 'No file was sent.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server has no tmp directory configured.',
            UPLOAD_ERR_CANT_WRITE => 'Server failed to write the file.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
            default               => 'Upload failed (code ' . $code . ').',
        };
    }

    private function uploadHtaccess(): string
    {
        return <<<'HTACCESS'
# Hardening for /uploads — we only ever serve static media, never execute PHP.
<FilesMatch "\.(php|phtml|phar|pl|py|cgi|sh|asp|aspx)$">
    Require all denied
</FilesMatch>
# Block dotfiles entirely.
<FilesMatch "^\.">
    Require all denied
</FilesMatch>
Options -Indexes -ExecCGI
HTACCESS;
    }
}
