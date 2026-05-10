<?php
declare(strict_types=1);

namespace Devithor\Controllers\Admin;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;
use Devithor\View;

/**
 * Settings — typed key/value store grouped into tabs (general, features,
 * notifications, payments, maintenance). Schema lives in migration 007 with
 * seed rows; this controller only reads/writes existing keys, never creates
 * new ones (a missing key is a code change, not a UI action).
 *
 * Secret values (SMTP password, API keys) are stored as plaintext in the DB
 * — relying on row access being limited to admins. If you ever need at-rest
 * encryption, wrap the value column with sodium_crypto_secretbox + APP_KEY.
 */
final class SettingsController
{
    private const GROUPS = [
        'general'       => 'General',
        'features'      => 'Features',
        'notifications' => 'Notifications',
        'payments'      => 'Payments',
        'maintenance'   => 'Maintenance',
    ];

    public function index(Request $req): never
    {
        $group = (string) $req->input('group', 'general');
        if (!isset(self::GROUPS[$group])) $group = 'general';

        $rows = Database::all(
            'SELECT * FROM app_settings WHERE `group` = ? ORDER BY sort_order ASC, label ASC',
            [$group],
        );

        Response::html(View::render('admin/settings/index', [
            'group'        => $group,
            'groups'       => self::GROUPS,
            'rows'         => $rows,
            'page'         => 'settings',
            'me'           => $req->params['user'],
            'flash'        => $this->popFlash(),
        ]));
    }

    public function update(Request $req): never
    {
        $group = (string) $req->input('group', 'general');
        if (!isset(self::GROUPS[$group])) {
            $this->setFlash('Unknown settings group.', 'error');
            Response::redirect('/admin/settings');
        }

        $rows = Database::all(
            'SELECT `key`, value_type, is_secret FROM app_settings WHERE `group` = ?',
            [$group],
        );

        $updated = 0;
        foreach ($rows as $row) {
            $key  = $row['key'];
            $type = $row['value_type'];
            $isSecret = (int) $row['is_secret'] === 1;

            // Secret fields: empty input means "don't change" so admins can
            // edit a non-secret in the same group without re-typing API keys.
            $raw = $req->input($key);
            if ($raw === null) continue;
            if ($isSecret && $raw === '') continue;

            $value = match ($type) {
                'BOOL'   => $req->input($key) ? '1' : '0',
                'INT'    => (string) (int) $raw,
                'JSON'   => $this->normaliseJson((string) $raw),
                default  => trim((string) $raw),
            };

            // Checkbox absent in POST when unchecked — handle BOOL re-check.
            if ($type === 'BOOL' && !isset($_POST[$key])) {
                $value = '0';
            }

            Database::exec(
                'UPDATE app_settings SET `value` = ? WHERE `key` = ?',
                [$value, $key],
            );
            $updated++;
        }

        $this->setFlash("Saved $updated setting(s).", 'success');
        Response::redirect('/admin/settings?group=' . urlencode($group));
    }

    private function normaliseJson(string $raw): string
    {
        $decoded = json_decode($raw, true);
        return json_last_error() === JSON_ERROR_NONE ? json_encode($decoded) : $raw;
    }

    private function setFlash(string $message, string $kind = 'success'): void
    {
        $_SESSION['flash'] = ['message' => $message, 'kind' => $kind];
    }

    private function popFlash(): ?array
    {
        $f = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $f;
    }
}
