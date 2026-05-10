<?php
declare(strict_types=1);

namespace Devithor;

/**
 * Video URL parsing + embed building.
 *
 * Pasted-URL UX: an admin types or pastes any video URL into the lesson
 * editor; [Video::detect] figures out which provider it belongs to and
 * extracts the canonical id (or full URL for HLS/MP4). The admin doesn't
 * have to pick a provider from a dropdown — but they can override.
 *
 * Recommended hosting strategy (cheapest → priciest):
 *   1. YouTube unlisted    — free, infinite bandwidth, simplest auth
 *   2. HLS .m3u8 on R2/S3 + Cloudflare in front   — pennies per GB
 *   3. Cloudflare Stream   — paid, baked-in DRM + signed URLs
 *
 * Embeds default to privacy-mode (youtube-nocookie.com) and disable
 * keyboard shortcuts that leak the original URL.
 */
final class Video
{
    public const PROVIDER_YOUTUBE    = 'YOUTUBE';
    public const PROVIDER_VIMEO      = 'VIMEO';
    public const PROVIDER_HLS        = 'HLS';
    public const PROVIDER_MP4        = 'MP4';
    public const PROVIDER_CLOUDFLARE = 'CLOUDFLARE';
    public const PROVIDER_OTHER      = 'OTHER';

    /**
     * Detect provider + canonical id from a free-form URL.
     *
     * @return array{
     *     provider:string,
     *     id:string,
     *     normalized_url:string,
     *     thumbnail_url:?string,
     *     embed_url:?string
     * }
     */
    public static function detect(string $url): array
    {
        $url = trim($url);
        if ($url === '') return self::blank();

        // ---- YouTube ----
        // youtu.be/<id>, youtube.com/watch?v=<id>, youtube.com/embed/<id>,
        // youtube.com/shorts/<id>, m.youtube.com/...
        if (preg_match(
            '~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|v/))([A-Za-z0-9_-]{6,15})~',
            $url, $m,
        )) {
            $id = $m[1];
            return [
                'provider'       => self::PROVIDER_YOUTUBE,
                'id'             => $id,
                'normalized_url' => "https://www.youtube.com/watch?v=$id",
                'thumbnail_url'  => "https://i.ytimg.com/vi/$id/hqdefault.jpg",
                'embed_url'      => self::buildEmbed(self::PROVIDER_YOUTUBE, $id),
            ];
        }

        // ---- Vimeo ----
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            $id = $m[1];
            return [
                'provider'       => self::PROVIDER_VIMEO,
                'id'             => $id,
                'normalized_url' => "https://vimeo.com/$id",
                'thumbnail_url'  => null, // requires API call
                'embed_url'      => "https://player.vimeo.com/video/$id?dnt=1",
            ];
        }

        // ---- Cloudflare Stream ----
        // customer-<hash>.cloudflarestream.com/<videoid>/...
        if (preg_match('~cloudflarestream\.com/([a-f0-9]{32})~i', $url, $m)) {
            $id = $m[1];
            return [
                'provider'       => self::PROVIDER_CLOUDFLARE,
                'id'             => $id,
                'normalized_url' => $url,
                'thumbnail_url'  => "https://customer-stream.cloudflarestream.com/$id/thumbnails/thumbnail.jpg",
                'embed_url'      => "https://iframe.videodelivery.net/$id",
            ];
        }

        // ---- HLS .m3u8 ----
        if (preg_match('~\.m3u8(\?.*)?$~i', $url)) {
            return [
                'provider'       => self::PROVIDER_HLS,
                'id'             => $url,
                'normalized_url' => $url,
                'thumbnail_url'  => null,
                'embed_url'      => $url, // HTML5 + hls.js plays it directly
            ];
        }

        // ---- MP4 / WebM / Mov ----
        if (preg_match('~\.(mp4|webm|mov|m4v)(\?.*)?$~i', $url)) {
            return [
                'provider'       => self::PROVIDER_MP4,
                'id'             => $url,
                'normalized_url' => $url,
                'thumbnail_url'  => null,
                'embed_url'      => $url,
            ];
        }

        return [
            'provider'       => self::PROVIDER_OTHER,
            'id'             => $url,
            'normalized_url' => $url,
            'thumbnail_url'  => null,
            'embed_url'      => $url,
        ];
    }

    /** Build a privacy-friendly embed URL given provider + id. */
    public static function buildEmbed(string $provider, string $id, array $opts = []): string
    {
        switch ($provider) {
            case self::PROVIDER_YOUTUBE:
                $noCookie = (string) self::setting('video_youtube_no_cookie', '1') === '1';
                $host = $noCookie ? 'www.youtube-nocookie.com' : 'www.youtube.com';
                $params = http_build_query(array_merge([
                    'rel'            => 0,         // never show "more from web"
                    'modestbranding' => 1,         // hide the YouTube logo as much as the ToS allows
                    'playsinline'    => 1,         // iOS won't force fullscreen
                    'iv_load_policy' => 3,         // hide annotations
                    'cc_load_policy' => 1,         // captions on by default if available
                ], $opts));
                return "https://$host/embed/$id?$params";

            case self::PROVIDER_VIMEO:
                return "https://player.vimeo.com/video/$id?dnt=1&pip=0&keyboard=0";

            case self::PROVIDER_CLOUDFLARE:
                return "https://iframe.videodelivery.net/$id";

            default:
                return $id; // already a URL for HLS / MP4
        }
    }

    /**
     * Best-effort metadata fetch via YouTube oEmbed (no API key needed).
     * Returns title + thumbnail + author + duration_iso (when available).
     */
    public static function youtubeOembed(string $videoId): array
    {
        $url = 'https://www.youtube.com/oembed?format=json&url=' . urlencode("https://www.youtube.com/watch?v=$videoId");
        $ctx = stream_context_create(['http' => ['timeout' => 3]]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) return [];
        $data = json_decode($body, true);
        if (!is_array($data)) return [];
        return [
            'title'         => (string) ($data['title'] ?? ''),
            'author_name'   => (string) ($data['author_name'] ?? ''),
            'thumbnail_url' => (string) ($data['thumbnail_url'] ?? ''),
            'html'          => (string) ($data['html'] ?? ''),
        ];
    }

    /**
     * Generate a time-limited token an Android client can present to the
     * playback endpoint. The token binds (lesson, user, expiry) so a leaked
     * URL can't be replayed by another account.
     */
    public static function signPlaybackToken(string $lessonId, string $userId, int $ttlSeconds = 3600): string
    {
        $key = (string) (getenv('APP_KEY') ?: '');
        $expires = time() + $ttlSeconds;
        $payload = "$lessonId|$userId|$expires";
        $sig = hash_hmac('sha256', $payload, $key);
        return base64_encode("$payload|$sig");
    }

    /** @return array{lesson_id:string, user_id:string, expires:int}|null */
    public static function verifyPlaybackToken(string $token): ?array
    {
        $key = (string) (getenv('APP_KEY') ?: '');
        $decoded = base64_decode($token, true);
        if ($decoded === false || substr_count($decoded, '|') !== 3) return null;
        [$lessonId, $userId, $expires, $sig] = explode('|', $decoded, 4);
        if ((int) $expires < time()) return null;
        $expected = hash_hmac('sha256', "$lessonId|$userId|$expires", $key);
        if (!hash_equals($expected, $sig)) return null;
        return ['lesson_id' => $lessonId, 'user_id' => $userId, 'expires' => (int) $expires];
    }

    /** Tiny settings reader so we don't pull the whole settings module here. */
    public static function setting(string $key, string $default = ''): string
    {
        static $cache = [];
        if (isset($cache[$key])) return $cache[$key];
        $val = Database::scalar('SELECT `value` FROM app_settings WHERE `key` = ?', [$key]);
        return $cache[$key] = ($val !== null ? (string) $val : $default);
    }

    private static function blank(): array
    {
        return [
            'provider'       => self::PROVIDER_OTHER,
            'id'             => '',
            'normalized_url' => '',
            'thumbnail_url'  => null,
            'embed_url'      => null,
        ];
    }
}
