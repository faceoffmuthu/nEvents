<?php

declare(strict_types=1);

namespace NEvents\Services\Events;

/**
 * SSRF protection for external registration URL redirects.
 * Never follow user-supplied URLs without validation.
 */
class RegistrationRedirectService
{
    private const BLOCKED_RANGES = [
        ['10.0.0.0',    '10.255.255.255'],
        ['172.16.0.0',  '172.31.255.255'],
        ['192.168.0.0', '192.168.255.255'],
        ['127.0.0.0',   '127.255.255.255'],
        ['169.254.0.0', '169.254.255.255'], // link-local
        ['100.64.0.0',  '100.127.255.255'], // CGNAT
        ['::1',         '::1'],
        ['0.0.0.0',     '0.0.0.0'],
    ];

    private const BLOCKED_HOSTS = [
        'localhost',
        '169.254.169.254',  // AWS metadata
        'metadata.google.internal',
        'metadata.internal',
    ];

    public function isSafeExternalUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (!$parsed) return false;

        // Only http/https allowed
        $scheme = strtolower($parsed['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) return false;

        $host = strtolower($parsed['host'] ?? '');
        if (empty($host)) return false;

        // Block known bad hostnames
        if (in_array($host, self::BLOCKED_HOSTS, true)) return false;

        // Block hosts with internal-looking names
        if (str_ends_with($host, '.internal') || str_ends_with($host, '.local')) return false;

        // Resolve and check IP
        $ips = gethostbynamel($host);
        if ($ips === false) return false; // Cannot resolve

        foreach ($ips as $ip) {
            if (!$this->isPublicIp($ip)) return false;
        }

        return true;
    }

    private function isPublicIp(string $ip): bool
    {
        $long = ip2long($ip);
        if ($long === false) return false; // IPv6: allow for now (expand as needed)

        foreach (self::BLOCKED_RANGES as [$start, $end]) {
            if ($long >= ip2long($start) && $long <= ip2long($end)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Single source of truth for "can this event's Register Now button be
     * active right now" — used identically by the event detail page and the
     * register-redirect route, so they never disagree.
     *
     * @return 'open'|'cancelled'|'completed'|'no_link'
     */
    public function determineState(array $event, array $occurrences): string
    {
        if (($event['status'] ?? '') === 'cancelled') {
            return 'cancelled';
        }

        if (empty($event['registration_url'])) {
            return 'no_link';
        }

        if (!empty($occurrences)) {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $hasUpcoming = false;
            foreach ($occurrences as $occ) {
                $cutoffRaw = !empty($occ['end_at_utc']) ? $occ['end_at_utc'] : $occ['start_at_utc'];
                $cutoff = new \DateTimeImmutable($cutoffRaw, new \DateTimeZone('UTC'));
                if ($cutoff >= $now) {
                    $hasUpcoming = true;
                    break;
                }
            }
            if (!$hasUpcoming) {
                return 'completed';
            }
        }

        return 'open';
    }
}
