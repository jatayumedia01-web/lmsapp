<?php
declare(strict_types=1);

namespace Devithor\Controllers\Api;

use Devithor\Database;
use Devithor\Geolocation;
use Devithor\Request;
use Devithor\Response;

/**
 * Behavior + device + session tracking ingest. The mobile app calls these
 * routes through the existing Bearer-token middleware, so [Auth::requireUser]
 * already populated $request->params['user'].
 *
 * Three endpoints:
 *   POST /api/v1/track/event           — single event
 *   POST /api/v1/track/batch           — many events in one round-trip (preferred)
 *   POST /api/v1/track/session/start   — open a session, capture device + geo
 *   POST /api/v1/track/session/end     — close a session, commit duration
 *
 * Device identity is the (user_id, device_id) pair; the device_id is a UUID
 * the app generates on first launch and stores locally. Returning the
 * `device_pk` lets the app stamp it on subsequent events without re-sending
 * the full device descriptor.
 */
final class TrackingController
{
    private const MAX_BATCH = 200;
    private const MAX_PROPS_BYTES = 8000;

    public function singleEvent(Request $req): never
    {
        $user = $req->params['user'];
        $body = $req->body;

        $devicePk  = $this->upsertDeviceIfPresent($user['id'], (array) ($body['device'] ?? []));
        $sessionId = isset($body['session_id']) ? (string) $body['session_id'] : null;
        $event     = (array) ($body['event'] ?? $body); // accept either nested or flat

        $stored = $this->insertEvent($user['id'], $sessionId, $devicePk, $event);
        $this->touchUserAndSession($user['id'], $sessionId);

        Response::json(['ok' => true, 'event_id' => $stored, 'device_pk' => $devicePk]);
    }

    public function batchEvents(Request $req): never
    {
        $user   = $req->params['user'];
        $body   = $req->body;
        $events = (array) ($body['events'] ?? []);
        if (count($events) === 0) {
            Response::json(['ok' => true, 'inserted' => 0]);
        }
        if (count($events) > self::MAX_BATCH) {
            Response::json(['error' => 'batch too large', 'max' => self::MAX_BATCH], 400);
        }

        $devicePk  = $this->upsertDeviceIfPresent($user['id'], (array) ($body['device'] ?? []));
        $sessionId = isset($body['session_id']) ? (string) $body['session_id'] : null;

        $inserted = 0;
        foreach ($events as $ev) {
            if (!is_array($ev)) continue;
            $this->insertEvent($user['id'], $sessionId, $devicePk, $ev);
            $inserted++;
        }
        $this->touchUserAndSession($user['id'], $sessionId, $inserted);

        Response::json(['ok' => true, 'inserted' => $inserted, 'device_pk' => $devicePk]);
    }

    public function sessionStart(Request $req): never
    {
        $user = $req->params['user'];
        $body = $req->body;

        $devicePk = $this->upsertDeviceIfPresent($user['id'], (array) ($body['device'] ?? []));
        $ip       = Geolocation::clientIp();
        $geo      = Geolocation::lookup($ip);
        $sessionId = isset($body['session_id'])
            ? (string) $body['session_id']
            : ('s_' . bin2hex(random_bytes(8)));

        Database::exec(
            'INSERT INTO user_sessions
                (id, user_id, device_pk, started_at, last_event_at,
                 ip_address, country_code, country, region, city,
                 latitude, longitude, timezone, user_agent)
             VALUES (?, ?, ?, NOW(), NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                last_event_at = NOW()',
            [
                $sessionId, $user['id'], $devicePk, $ip,
                $geo['country_code'], $geo['country'], $geo['region'], $geo['city'],
                $geo['latitude'], $geo['longitude'], $geo['timezone'],
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            ],
        );

        Database::exec(
            'UPDATE users SET last_sign_in_at = NOW() WHERE id = ?',
            [$user['id']],
        );

        Response::json([
            'ok'         => true,
            'session_id' => $sessionId,
            'device_pk'  => $devicePk,
            'geo'        => [
                'country' => $geo['country'],
                'city'    => $geo['city'],
            ],
        ]);
    }

    public function sessionEnd(Request $req): never
    {
        $user = $req->params['user'];
        $sessionId = (string) $req->input('session_id', '');
        if ($sessionId === '') {
            Response::json(['error' => 'session_id required'], 400);
        }
        Database::exec(
            'UPDATE user_sessions
             SET ended_at = NOW(),
                 duration_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW())
             WHERE id = ? AND user_id = ?',
            [$sessionId, $user['id']],
        );
        Response::json(['ok' => true]);
    }

    // ---- internals ------------------------------------------------------

    private function insertEvent(string $userId, ?string $sessionId, ?string $devicePk, array $ev): int
    {
        $name = trim((string) ($ev['event'] ?? $ev['name'] ?? ''));
        if ($name === '' || strlen($name) > 80) return 0;

        $occurredAt = $this->parseOccurredAt($ev['occurred_at'] ?? null);

        $props = $ev['props'] ?? null;
        $propsJson = null;
        if (is_array($props) && $props !== []) {
            $encoded = json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded !== false && strlen($encoded) <= self::MAX_PROPS_BYTES) {
                $propsJson = $encoded;
            }
        }

        $valueNum = $ev['value'] ?? null;
        if ($valueNum !== null && !is_numeric($valueNum)) $valueNum = null;

        Database::exec(
            'INSERT INTO user_events
                (user_id, session_id, device_pk, event_name, screen,
                 course_id, lesson_id, value_numeric, props_json,
                 occurred_at, received_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(3))',
            [
                $userId, $sessionId, $devicePk, $name,
                isset($ev['screen']) ? substr((string) $ev['screen'], 0, 80) : null,
                isset($ev['course_id']) ? substr((string) $ev['course_id'], 0, 64) : null,
                isset($ev['lesson_id']) ? substr((string) $ev['lesson_id'], 0, 64) : null,
                $valueNum !== null ? (float) $valueNum : null,
                $propsJson,
                $occurredAt,
            ],
        );
        return (int) Database::pdo()->lastInsertId();
    }

    private function upsertDeviceIfPresent(string $userId, array $device): ?string
    {
        $deviceId = trim((string) ($device['device_id'] ?? ''));
        if ($deviceId === '') return null;

        $existing = Database::one(
            'SELECT id FROM user_devices WHERE user_id = ? AND device_id = ?',
            [$userId, $deviceId],
        );
        $platform = strtoupper((string) ($device['platform'] ?? 'OTHER'));
        if (!in_array($platform, ['ANDROID', 'IOS', 'WEB', 'OTHER'], true)) $platform = 'OTHER';

        if ($existing) {
            Database::exec(
                'UPDATE user_devices SET
                    platform = ?, device_type = ?, os_name = ?, os_version = ?,
                    app_version = ?, model = ?, manufacturer = ?, screen = ?,
                    language = ?, timezone = ?, push_token = COALESCE(?, push_token),
                    last_seen_at = NOW()
                 WHERE id = ?',
                [
                    $platform,
                    substr((string) ($device['device_type'] ?? 'unknown'), 0, 40),
                    substr((string) ($device['os_name']     ?? ''), 0, 40),
                    substr((string) ($device['os_version']  ?? ''), 0, 40),
                    substr((string) ($device['app_version'] ?? ''), 0, 40),
                    substr((string) ($device['model']       ?? ''), 0, 120),
                    substr((string) ($device['manufacturer']?? ''), 0, 80),
                    substr((string) ($device['screen']      ?? ''), 0, 40),
                    substr((string) ($device['language']    ?? ''), 0, 20),
                    substr((string) ($device['timezone']    ?? ''), 0, 60),
                    isset($device['push_token']) ? substr((string) $device['push_token'], 0, 255) : null,
                    $existing['id'],
                ],
            );
            return $existing['id'];
        }

        $pk = 'd_' . bin2hex(random_bytes(8));
        Database::exec(
            'INSERT INTO user_devices
                (id, user_id, device_id, platform, device_type, os_name, os_version,
                 app_version, model, manufacturer, screen, language, timezone, push_token,
                 first_seen_at, last_seen_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [
                $pk, $userId, $deviceId, $platform,
                substr((string) ($device['device_type'] ?? 'unknown'), 0, 40),
                substr((string) ($device['os_name']     ?? ''), 0, 40),
                substr((string) ($device['os_version']  ?? ''), 0, 40),
                substr((string) ($device['app_version'] ?? ''), 0, 40),
                substr((string) ($device['model']       ?? ''), 0, 120),
                substr((string) ($device['manufacturer']?? ''), 0, 80),
                substr((string) ($device['screen']      ?? ''), 0, 40),
                substr((string) ($device['language']    ?? ''), 0, 20),
                substr((string) ($device['timezone']    ?? ''), 0, 60),
                isset($device['push_token']) ? substr((string) $device['push_token'], 0, 255) : null,
            ],
        );
        return $pk;
    }

    private function touchUserAndSession(string $userId, ?string $sessionId, int $eventCount = 1): void
    {
        // last_sign_in_at doubles as "last activity" — cheap to keep current.
        Database::exec('UPDATE users SET last_sign_in_at = NOW() WHERE id = ?', [$userId]);

        if ($sessionId !== null) {
            Database::exec(
                'UPDATE user_sessions
                 SET last_event_at = NOW(),
                     events_count = events_count + ?
                 WHERE id = ? AND user_id = ?',
                [$eventCount, $sessionId, $userId],
            );
        }
    }

    private function parseOccurredAt($input): string
    {
        if (is_numeric($input)) {
            // Accept either seconds or milliseconds.
            $millis = ((int) $input) > 1_000_000_000_000 ? (int) $input : ((int) $input) * 1000;
            $sec = (int) floor($millis / 1000);
            $msPart = $millis % 1000;
            return date('Y-m-d H:i:s', $sec) . '.' . str_pad((string) $msPart, 3, '0', STR_PAD_LEFT);
        }
        if (is_string($input) && $input !== '') {
            $ts = strtotime($input);
            if ($ts !== false) return date('Y-m-d H:i:s.000', $ts);
        }
        return date('Y-m-d H:i:s.000');
    }
}
