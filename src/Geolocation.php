<?php
declare(strict_types=1);

namespace Devithor;

/**
 * Resolve an IPv4/IPv6 address to country/city/coords.
 *
 * Strategy (cheapest-first):
 *   1. In-process static cache (free, request-scoped).
 *   2. ip_geo_cache table (one DB hit, valid for 7 days).
 *   3. ip-api.com free HTTP endpoint, 2-second timeout.
 *      The free tier has a soft 45 req/min limit and is "non-commercial use"
 *      per their ToS — fine for ops dashboards. Swap to ipinfo.io / MaxMind
 *      if you ever exceed it.
 *
 * Always returns the same shape; on any failure (private IP, network down,
 * bad JSON) you get an array with all-null fields rather than an exception.
 */
final class Geolocation
{
    private const URL = 'http://ip-api.com/json/%s?fields=status,country,countryCode,region,regionName,city,lat,lon,timezone,isp';
    private const CACHE_TTL_DAYS = 7;
    private const TIMEOUT_SECONDS = 2;

    /** @var array<string, array> */
    private static array $memo = [];

    /**
     * @return array{country_code:?string,country:?string,region:?string,city:?string,latitude:?float,longitude:?float,timezone:?string,isp:?string}
     */
    public static function lookup(string $ip): array
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP) || self::isPrivate($ip)) {
            return self::unknown();
        }
        if (isset(self::$memo[$ip])) return self::$memo[$ip];

        // Cached row?
        $cached = Database::one(
            'SELECT country_code, country, region, city, latitude, longitude, timezone, isp
             FROM ip_geo_cache
             WHERE ip_address = ? AND lookup_at > DATE_SUB(NOW(), INTERVAL ? DAY)',
            [$ip, self::CACHE_TTL_DAYS],
        );
        if ($cached) {
            $cached = self::normaliseRow($cached);
            self::$memo[$ip] = $cached;
            return $cached;
        }

        // Live lookup
        $resolved = self::fetchRemote($ip);
        if ($resolved === null) {
            $resolved = self::unknown();
        }

        // Persist (also overwrites stale rows).
        Database::exec(
            'INSERT INTO ip_geo_cache
                (ip_address, country_code, country, region, city, latitude, longitude, timezone, isp, lookup_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                country_code = VALUES(country_code), country = VALUES(country),
                region = VALUES(region), city = VALUES(city),
                latitude = VALUES(latitude), longitude = VALUES(longitude),
                timezone = VALUES(timezone), isp = VALUES(isp),
                lookup_at = NOW()',
            [
                $ip, $resolved['country_code'], $resolved['country'], $resolved['region'],
                $resolved['city'], $resolved['latitude'], $resolved['longitude'],
                $resolved['timezone'], $resolved['isp'],
            ],
        );

        self::$memo[$ip] = $resolved;
        return $resolved;
    }

    /** Resolve the originating client IP, accounting for common reverse proxies. */
    public static function clientIp(): string
    {
        // Cloudflare / Hostinger / typical CDNs.
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'] as $header) {
            if (!empty($_SERVER[$header])) {
                $candidate = trim(explode(',', (string) $_SERVER[$header])[0]);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) return $candidate;
            }
        }
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    private static function fetchRemote(string $ip): ?array
    {
        $url = sprintf(self::URL, urlencode($ip));
        $ctx = stream_context_create([
            'http' => ['timeout' => self::TIMEOUT_SECONDS, 'ignore_errors' => true],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) return null;

        $data = json_decode($body, true);
        if (!is_array($data) || ($data['status'] ?? '') !== 'success') return null;

        return [
            'country_code' => isset($data['countryCode']) ? (string) $data['countryCode'] : null,
            'country'      => isset($data['country'])     ? (string) $data['country']     : null,
            'region'       => isset($data['regionName'])  ? (string) $data['regionName']  : null,
            'city'         => isset($data['city'])        ? (string) $data['city']        : null,
            'latitude'     => isset($data['lat'])         ? (float)  $data['lat']         : null,
            'longitude'    => isset($data['lon'])         ? (float)  $data['lon']         : null,
            'timezone'     => isset($data['timezone'])    ? (string) $data['timezone']    : null,
            'isp'          => isset($data['isp'])         ? (string) $data['isp']         : null,
        ];
    }

    private static function normaliseRow(array $row): array
    {
        return [
            'country_code' => $row['country_code'] !== null ? (string) $row['country_code'] : null,
            'country'      => $row['country']      !== null ? (string) $row['country']      : null,
            'region'       => $row['region']       !== null ? (string) $row['region']       : null,
            'city'         => $row['city']         !== null ? (string) $row['city']         : null,
            'latitude'     => $row['latitude']     !== null ? (float)  $row['latitude']     : null,
            'longitude'    => $row['longitude']    !== null ? (float)  $row['longitude']    : null,
            'timezone'     => $row['timezone']     !== null ? (string) $row['timezone']     : null,
            'isp'          => $row['isp']          !== null ? (string) $row['isp']          : null,
        ];
    }

    private static function isPrivate(string $ip): bool
    {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    private static function unknown(): array
    {
        return [
            'country_code' => null, 'country' => null, 'region' => null, 'city' => null,
            'latitude' => null, 'longitude' => null, 'timezone' => null, 'isp' => null,
        ];
    }
}
