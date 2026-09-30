<?php

declare(strict_types=1);

namespace NEvents\Services\Ingestion;

use NEvents\Core\Application;
use NEvents\Services\Events\RegistrationRedirectService;
use NEvents\Services\Ingestion\Web\RobotsTxt;

abstract class AbstractSourceAdapter implements SourceAdapterInterface
{
    protected const MAX_FETCH_BYTES = 5_242_880; // 5 MB
    protected const MAX_REDIRECTS   = 5;

    /** Web page adapters obey robots.txt; API adapters (social) turn this off. */
    protected bool $respectRobots = true;

    /** @var array<string, RobotsTxt> host => rules (per process / scheduler run) */
    private static array $robots = [];
    /** @var array<string, float> host => microtime of last request */
    private static array $lastHit = [];

    public function __construct(
        protected RegistrationRedirectService $ssrf,
    ) {}

    /**
     * SSRF-safe, robots-respecting, polite GET. Returns the body; throws on
     * 4xx/5xx, network errors, SSRF blocks and robots.txt disallows.
     *
     * @param array<string> $headers Extra request headers ("Name: value")
     */
    protected function httpGet(string $url, int $timeoutSec = 15, array $headers = []): string
    {
        $res = $this->httpFetch($url, $timeoutSec, $headers);
        if ($res['status'] >= 400) {
            throw new \RuntimeException("HTTP {$res['status']} from " . $this->redact($url));
        }
        return $res['body'];
    }

    /**
     * Redirects are followed MANUALLY so every hop is re-validated (the
     * destination of a redirect is attacker-controlled, and PHP's automatic
     * follow_location would happily fetch 169.254.169.254). Every hop is also
     * checked against that host's robots.txt. Error messages never include
     * the query string (API tokens live there).
     *
     * @return array{status:int, body:string, url:string}
     */
    protected function httpFetch(string $url, int $timeoutSec = 15, array $headers = [], bool $checkRobots = true): array
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (!$this->isFetchable($url)) {
                throw new \RuntimeException('SSRF: blocked URL ' . $this->redact($url));
            }
            if ($checkRobots && $this->respectRobots && !$this->robotsFor($url)->isAllowed($this->pathAndQuery($url))) {
                throw new RobotsDisallowedException('robots.txt disallows ' . $this->redact($url));
            }
            $this->politeWait($url);

            $ctx = stream_context_create([
                'http' => [
                    'method'          => 'GET',
                    'timeout'         => $timeoutSec,
                    'follow_location' => 0,
                    'ignore_errors'   => true,
                    'header'          => implode("\r\n", array_merge([
                        'User-Agent: ' . $this->userAgent(),
                        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,application/rss+xml,text/calendar,*/*;q=0.8',
                    ], $headers)) . "\r\n",
                ],
                'ssl' => [
                    'verify_peer'      => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $body = @file_get_contents($url, false, $ctx, 0, self::MAX_FETCH_BYTES);
            $responseHeaders = $http_response_header ?? [];
            $status = 0;
            if (isset($responseHeaders[0]) && preg_match('#^HTTP/\S+\s+(\d{3})#', $responseHeaders[0], $m)) {
                $status = (int) $m[1];
            }

            if ($status >= 300 && $status < 400) {
                $location = null;
                foreach ($responseHeaders as $h) {
                    if (stripos($h, 'Location:') === 0) {
                        $location = trim(substr($h, 9));
                    }
                }
                if (!$location) {
                    throw new \RuntimeException("Redirect without Location from " . $this->redact($url));
                }
                $url = $this->resolveUrl($url, $location);
                continue;
            }

            if ($body === false || $status === 0) {
                throw new \RuntimeException('Fetch failed: ' . $this->redact($url));
            }
            return ['status' => $status, 'body' => $body, 'url' => $url];
        }

        throw new \RuntimeException('Too many redirects: ' . $this->redact($url));
    }

    /**
     * robots.txt for the URL's host, fetched once per run. RFC 9309: a 4xx
     * robots.txt means "no rules" (allow), 5xx or unreachable means "assume
     * everything is disallowed".
     */
    protected function robotsFor(string $url): RobotsTxt
    {
        $p    = parse_url($url);
        $host = strtolower(($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : ''));
        if (isset(self::$robots[$host])) {
            return self::$robots[$host];
        }
        try {
            $res = $this->httpFetch($host . '/robots.txt', 10, [], false);
            self::$robots[$host] = match (true) {
                $res['status'] >= 500 => RobotsTxt::disallowAll(),
                $res['status'] >= 400 => RobotsTxt::allowAll(),
                default               => new RobotsTxt($res['body'], 'nevents-bot'),
            };
        } catch (\Throwable) {
            self::$robots[$host] = RobotsTxt::disallowAll();
        }
        return self::$robots[$host];
    }

    /** Waits so requests to one host are spaced by max(Crawl-delay, configured minimum). */
    private function politeWait(string $url): void
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $minMs = (int) Application::getInstance()->config('app.discovery.min_delay_ms', 1000);
        if ($this->respectRobots && isset(self::$robots[$this->originOf($url)]) && ($cd = self::$robots[$this->originOf($url)]->crawlDelay()) !== null) {
            $minMs = max($minMs, (int) min(30000, $cd * 1000));   // cap: never stall a run for > 30s per request
        }
        if (isset(self::$lastHit[$host])) {
            $waitUs = (int) (($minMs / 1000 - (microtime(true) - self::$lastHit[$host])) * 1_000_000);
            if ($waitUs > 0) {
                usleep($waitUs);
            }
        }
        self::$lastHit[$host] = microtime(true);
    }

    /**
     * Public internet only. DISCOVERY_TEST_HOSTS (e.g. "127.0.0.1:8099") lets
     * the test suite crawl a local fixture site — ignored in production.
     */
    private function isFetchable(string $url): bool
    {
        if ($this->ssrf->isSafeExternalUrl($url)) {
            return true;
        }
        $app = Application::getInstance();
        if ($app->config('app.env') === 'production') {
            return false;
        }
        $p = parse_url($url);
        $hostPort = strtolower(($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : ''));
        $allowed  = array_filter(array_map('trim', explode(',', (string) $app->config('app.discovery.test_hosts', ''))));
        return in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) && in_array($hostPort, $allowed, true);
    }

    protected function userAgent(): string
    {
        return (string) Application::getInstance()->config('app.discovery.user_agent', 'NEventsBot/1.0');
    }

    protected function pathAndQuery(string $url): string
    {
        $p = parse_url($url);
        return ($p['path'] ?? '/') . (isset($p['query']) ? '?' . $p['query'] : '');
    }

    private function originOf(string $url): string
    {
        $p = parse_url($url);
        return strtolower(($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : ''));
    }

    /** Resolves a (possibly relative) link against the page it appeared on. */
    protected function absoluteUrl(string $base, string $href): ?string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($href === '' || preg_match('#^(mailto|tel|javascript|data):#i', $href) || str_starts_with($href, '#')) {
            return null;
        }
        $abs = $this->resolveUrl($base, $href);
        $abs = preg_replace('/#.*$/', '', $abs);
        return preg_match('#^https?://#i', (string) $abs) ? $abs : null;
    }

    public function healthCheck(array $sourceConfig): bool
    {
        try {
            $url = $sourceConfig['health_url'] ?? $sourceConfig['url'] ?? '';
            if (!$url) return false;
            $this->httpGet($url, 5);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function validate(array $normalized): array
    {
        $errors = [];
        if (empty($normalized['title'])) $errors[] = 'title is required';
        if (empty($normalized['source_url'])) $errors[] = 'source_url is required';
        return $errors;
    }

    /** Web adapters just need a URL in config_json. */
    public function isConfigured(array $sourceConfig): bool
    {
        return !empty($sourceConfig['url']);
    }

    public function getSourceMetadata(array $sourceConfig): array
    {
        return [
            'platform'           => 'web',
            'acquisition_method' => 'permitted_html',
            'credentials'        => [],
            'configured'         => $this->isConfigured($sourceConfig),
            'limitations'        => 'Only fetch sites whose terms and robots.txt permit it.',
        ];
    }

    protected function redact(string $url): string
    {
        $p = parse_url($url);
        return ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '?') . ($p['path'] ?? '');
    }

    private function resolveUrl(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $b = parse_url($base);
        $origin = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($location, '//')) {
            return $b['scheme'] . ':' . $location;
        }
        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }
        if (str_starts_with($location, '?')) {
            return $origin . ($b['path'] ?? '/') . $location;
        }
        $path = $b['path'] ?? '/';
        $dir  = str_ends_with($path, '/') ? rtrim($path, '/') : rtrim(str_replace('\\', '/', dirname($path)), '/');
        // normalise ./ and ../ segments
        $parts = [];
        foreach (explode('/', $dir . '/' . $location) as $seg) {
            if ($seg === '..') { array_pop($parts); } elseif ($seg !== '.') { $parts[] = $seg; }
        }
        return $origin . '/' . ltrim(implode('/', $parts), '/');
    }
}
