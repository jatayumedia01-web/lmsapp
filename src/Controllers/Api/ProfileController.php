<?php
declare(strict_types=1);

namespace Devithor\Controllers\Api;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;
use Devithor\Validator;

/**
 * Student profile management — all routes require authentication.
 *
 * PATCH /api/v1/profile                      — partial profile update
 * POST  /api/v1/profile/complete-onboarding  — mark onboarding done
 * POST  /api/v1/profile/avatar               — upload profile picture (multipart)
 */
final class ProfileController
{
    /** Allowed MIME types for avatar uploads. */
    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /** Maximum upload size in bytes (5 MB). */
    private const MAX_BYTES = 5 * 1024 * 1024;

    /** Writable columns the client may update via PATCH /profile. */
    private const UPDATABLE_FIELDS = [
        'full_name'   => ['min:2', 'max:190'],
        'dob'         => [],    // validated as date below
        'gender'      => ['in:MALE,FEMALE,OTHER,PREFER_NOT_TO_SAY'],
        'mobile'      => ['max:20'],
        'whatsapp'    => ['max:20'],
        'school_name' => ['max:255'],
        'class_id'    => ['max:64'],
        'city'        => ['max:100'],
        'state'       => ['max:100'],
        'address'     => [],
    ];

    // ── PATCH /api/v1/profile ─────────────────────────────────────────────────

    public function update(Request $req): never
    {
        $user = $req->params['user'];

        // Build a dynamic list of rules for only the fields that were sent.
        $ruleMap = [];
        foreach (self::UPDATABLE_FIELDS as $field => $rules) {
            if (array_key_exists($field, $req->body)) {
                $ruleMap[$field] = $rules;
            }
        }

        if (!empty($ruleMap)) {
            $errors = Validator::check($req->body, $ruleMap);
            if ($errors) {
                Response::json(['errors' => $errors], 422);
            }
        }

        // Validate `dob` separately (date format YYYY-MM-DD).
        if (array_key_exists('dob', $req->body)) {
            $dob = trim((string) $req->body['dob']);
            if ($dob !== '' && !\DateTime::createFromFormat('Y-m-d', $dob)) {
                Response::json(['errors' => ['dob' => 'dob must be a valid date (YYYY-MM-DD)']], 422);
            }
        }

        // Collect only the fields that are present in the request body.
        $setClauses = [];
        $params     = [];

        foreach (self::UPDATABLE_FIELDS as $field => $_) {
            if (!array_key_exists($field, $req->body)) {
                continue;
            }
            $value = $req->body[$field];
            // Normalise empty string → null for nullable columns.
            if ($value === '' || $value === null) {
                $value = null;
            }
            $setClauses[] = "`$field` = ?";
            $params[]     = $value;
        }

        if (empty($setClauses)) {
            Response::json(['error' => 'no_fields', 'message' => 'No updatable fields provided.'], 422);
        }

        $params[] = $user['id'];
        Database::exec(
            'UPDATE users SET ' . implode(', ', $setClauses) . ' WHERE id = ?',
            $params,
        );

        $updated = Database::one('SELECT * FROM users WHERE id = ?', [$user['id']]);
        Response::json(['user' => $this->shape($updated)]);
    }

    // ── POST /api/v1/profile/complete-onboarding ──────────────────────────────

    public function completeOnboarding(Request $req): never
    {
        $user = $req->params['user'];

        Database::exec(
            'UPDATE users SET onboarding_completed = 1 WHERE id = ?',
            [$user['id']],
        );

        $updated = Database::one('SELECT * FROM users WHERE id = ?', [$user['id']]);
        Response::json(['user' => $this->shape($updated)]);
    }

    // ── POST /api/v1/profile/avatar ───────────────────────────────────────────

    public function uploadAvatar(Request $req): never
    {
        $user = $req->params['user'];

        if (empty($_FILES['avatar'])) {
            Response::json(['error' => 'missing_file', 'message' => 'No file uploaded. Send a multipart field named "avatar".'], 422);
        }

        $file  = $_FILES['avatar'];
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($error !== UPLOAD_ERR_OK) {
            $msg = match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds maximum allowed size (5 MB).',
                UPLOAD_ERR_NO_TMP_DIR => 'Server misconfiguration: no temporary directory.',
                UPLOAD_ERR_CANT_WRITE => 'Server misconfiguration: cannot write temporary file.',
                default               => 'File upload failed (code ' . $error . ').',
            };
            Response::json(['error' => 'upload_error', 'message' => $msg], 422);
        }

        // Size check.
        if ((int) $file['size'] > self::MAX_BYTES) {
            Response::json(['error' => 'file_too_large', 'message' => 'Avatar must be 5 MB or smaller.'], 422);
        }

        // MIME detection — use finfo for reliability (ignores the client-supplied type).
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!array_key_exists($mimeType, self::ALLOWED_MIMES)) {
            Response::json([
                'error'   => 'invalid_mime',
                'message' => 'Only JPEG, PNG, and WebP images are allowed.',
                'got'     => $mimeType,
            ], 422);
        }

        $ext       = self::ALLOWED_MIMES[$mimeType];
        $filename  = $user['id'] . '_' . time() . '.' . $ext;
        $uploadDir = dirname(__DIR__, 4) . '/public/uploads/avatars/';

        // Ensure directory exists (idempotent).
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destination = $uploadDir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            Response::json(['error' => 'save_failed', 'message' => 'Could not save the uploaded file. Check server permissions.'], 500);
        }

        // Delete the previous avatar file to avoid stale accumulation.
        $oldUrl = $user['profile_picture_url'] ?? null;
        if ($oldUrl) {
            $oldPath = dirname(__DIR__, 4) . '/public' . $oldUrl;
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        $avatarUrl = '/uploads/avatars/' . $filename;

        Database::exec(
            'UPDATE users SET profile_picture_url = ? WHERE id = ?',
            [$avatarUrl, $user['id']],
        );

        Response::json(['avatar_url' => $avatarUrl]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Return the full profile shape for the Android domain model.
     * Keep in sync with AuthController::shape().
     */
    private function shape(array $u): array
    {
        return [
            'id'                   => $u['id'],
            'email'                => $u['email'],
            'full_name'            => $u['full_name'],
            'role'                 => $u['role'],
            'avatar_url'           => $u['profile_picture_url'] ?? $u['avatar_url'] ?? null,
            'tenant_id'            => $u['tenant_id'] ?? 'default',
            'xp'                   => (int) ($u['xp'] ?? 0),
            'streak_days'          => (int) ($u['streak_days'] ?? 0),
            'dob'                  => $u['dob'] ?? null,
            'gender'               => $u['gender'] ?? null,
            'mobile'               => $u['mobile'] ?? null,
            'whatsapp'             => $u['whatsapp'] ?? null,
            'school_name'          => $u['school_name'] ?? null,
            'class_id'             => $u['class_id'] ?? null,
            'city'                 => $u['city'] ?? null,
            'state'                => $u['state'] ?? null,
            'address'              => $u['address'] ?? null,
            'onboarding_completed' => (int) ($u['onboarding_completed'] ?? 0),
        ];
    }
}
