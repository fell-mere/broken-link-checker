<?php

namespace craigclement\craftbrokenlinks\helpers;

/**
 * UrlSafety guards outbound HTTP requests against SSRF.
 *
 * Links that resolve to private, loopback, link-local, or otherwise reserved
 * IP ranges are rejected unless their host matches one of the site's own
 * hosts — so internal link checking still works on local/staging
 * environments while requests to cloud metadata endpoints, internal
 * services, etc. are blocked.
 */
class UrlSafety
{
    /**
     * Whether a URL is safe to request.
     *
     * @param string $url The absolute URL to test.
     * @param string[] $allowedHosts Lower-cased hostnames that are always allowed (the site's own hosts).
     * @return bool
     */
    public static function isAllowedUrl(string $url, array $allowedHosts = []): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }

        // Site's own hosts are always allowed, even on private/local addresses.
        if (in_array(strtolower($host), $allowedHosts, true)) {
            return true;
        }

        $ips = self::resolveIps($host);

        // Unresolvable host: not an SSRF risk (there's no internal target to
        // reach). Let the request proceed so the dead link is reported as
        // unreachable rather than being silently skipped — detecting these is
        // the whole point of the plugin.
        if ($ips === []) {
            return true;
        }

        // Block only if the host actually resolves to a private/reserved IP.
        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Collect every IP a host resolves to (or the host itself if it is an IP).
     *
     * @return string[]
     */
    private static function resolveIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (!empty($record['ip'])) {
                    $ips[] = $record['ip'];
                }
                if (!empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if (!$ips) {
            $resolved = @gethostbynamel($host);
            if (is_array($resolved)) {
                $ips = $resolved;
            }
        }

        return $ips;
    }

    /**
     * Whether an IP address is a routable public address.
     */
    private static function isPublicIp(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * The lower-cased hostnames of every site, for use as an allowlist.
     *
     * @param \craft\models\Site[] $sites
     * @return string[]
     */
    public static function siteHosts(array $sites): array
    {
        $hosts = [];
        foreach ($sites as $site) {
            $baseUrl = $site->getBaseUrl();
            if (!$baseUrl) {
                continue;
            }
            $host = parse_url($baseUrl, PHP_URL_HOST);
            if ($host) {
                $hosts[] = strtolower($host);
            }
        }

        return array_values(array_unique($hosts));
    }
}
