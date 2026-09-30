<?php

declare(strict_types=1);

namespace NEvents\Services\Security;

/**
 * Syntactic validation for URLs users type into forms (registration link,
 * organizer website, social profile, map link). Deliberately does NOT resolve
 * DNS — form submission must not depend on network lookups. Anything the
 * server later FETCHES goes through RegistrationRedirectService::isSafeExternalUrl()
 * (DNS + private-range checks) inside AbstractSourceAdapter::httpGet(), and the
 * registration redirect re-checks at click time.
 */
class UrlValidator
{
    private const BLOCKED_HOSTS = ['localhost', 'metadata.google.internal', 'metadata.internal'];

    public static function isAcceptable(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2000 || preg_match('/[\s<>"\'`]/', $url)) {
            return false;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts  = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host   = strtolower(trim($parts['host'] ?? '', '[]'));

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false; // https://user:pass@host tricks
        }
        if (in_array($host, self::BLOCKED_HOSTS, true)
            || str_ends_with($host, '.local') || str_ends_with($host, '.internal') || str_ends_with($host, '.localhost')) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // Literal IPs: only public ranges
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }
        // Must look like a real domain name (has a dot and a TLD)
        return (bool) preg_match('/^([a-z0-9-]+\.)+[a-z]{2,}$/', $host);
    }
}
