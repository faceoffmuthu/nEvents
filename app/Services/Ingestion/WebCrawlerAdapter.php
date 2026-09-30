<?php

declare(strict_types=1);

namespace NEvents\Services\Ingestion;

/**
 * Keyless web crawler for public event websites (organizer, venue,
 * college, community, conference and event-platform sites).
 *
 *   start_urls ──► listing pages ──(follow_pattern: pagination, category
 *                  pages)──► more listing pages
 *                      └──(event_pattern)──► event detail pages
 *   every page ──► StructuredDataExtractor (JSON-LD + microdata) ──► candidates
 *
 * config_json:
 *   start_urls      ["https://example.org/events"]                 (required)
 *   event_pattern   "#/events?/[^/?]+/?$#"   regex for event detail links
 *                   (default: any link containing /event/ or /events/)
 *   follow_pattern  "#[?&]page=\d+#"         regex for listing links to crawl on
 *                   (default: none — only start pages are crawled for links)
 *   max_depth       2      listing-link hops from a start page
 *   max_pages       40     pages fetched per run (DISCOVERY_MAX_PAGES default)
 *   allow_hosts     ["events.example.org"]   extra hosts besides the start pages' own
 *   strip_query     true   drop ?query from event links (tracking params like
 *                   ?aff= / ?eventOrigin= would otherwise fetch one page many times)
 *
 * Every fetch obeys robots.txt and a per-host delay (AbstractSourceAdapter).
 * Links to other sites are never followed unless listed in allow_hosts.
 */
class WebCrawlerAdapter extends JsonLdEventAdapter
{
    private const DEFAULT_EVENT_PATTERN = '#/(events?|e)/[^/?\#]+#i';

    /** @var array<string, string> pages already fetched during discover() */
    private array $pageCache = [];

    public function isConfigured(array $config): bool
    {
        return !empty($config['start_urls']) || !empty($config['url']);
    }

    public function discover(array $config): array
    {
        $this->pageCache = [];
        $starts   = array_values(array_filter(array_map('strval', (array) ($config['start_urls'] ?? [$config['url'] ?? '']))));
        $budget   = max(1, (int) ($config['max_pages'] ?? \NEvents\Core\Application::getInstance()->config('app.discovery.max_pages', 40)));
        $maxDepth = max(0, (int) ($config['max_depth'] ?? 2));
        $eventRx  = $this->validRegex($config['event_pattern'] ?? null) ?? self::DEFAULT_EVENT_PATTERN;
        $followRx = $this->validRegex($config['follow_pattern'] ?? null);
        $strip    = !empty($config['strip_query']);

        $hosts = array_map('strtolower', (array) ($config['allow_hosts'] ?? []));
        foreach ($starts as $s) {
            $hosts[] = strtolower((string) parse_url($s, PHP_URL_HOST));
        }
        $hosts = array_unique($hosts);

        $queue   = array_map(fn($u) => [$u, 0], $starts);
        $listing = [];          // crawled listing pages (they may carry event data too)
        $events  = [];          // event detail pages
        $fetched = 0;

        while ($queue && $fetched < $budget) {
            [$url, $depth] = array_shift($queue);
            if (isset($listing[$url])) {
                continue;
            }
            try {
                $html = $this->httpGet($url);
            } catch (RobotsDisallowedException) {
                continue;                        // respected, silently skipped
            } catch (\Throwable $e) {
                \NEvents\Core\Log::get()->info('crawl_page_failed', ['url' => $this->redact($url), 'error' => $e->getMessage()]);
                continue;
            }
            $fetched++;
            $listing[$url] = true;
            $this->pageCache[$url] = $html;

            // links in the HTML, plus event URLs named only in the page's structured data
            $found = $this->links($html, $url);
            foreach ($this->structured->extract($html) as $ev) {
                if (is_string($ev['url'] ?? null) && ($abs = $this->absoluteUrl($url, $ev['url'])) !== null) {
                    $found[] = $abs;
                }
            }
            foreach (array_unique($found) as $link) {
                $host = strtolower((string) parse_url($link, PHP_URL_HOST));
                if (!in_array($host, $hosts, true) || isset($listing[$link])) {
                    continue;
                }
                if (preg_match($eventRx, $link)) {
                    $events[$strip ? strtok($link, '?#') : $link] = true;
                } elseif ($followRx && $depth < $maxDepth && preg_match($followRx, $link)) {
                    $queue[] = [$link, $depth + 1];
                }
            }
        }

        // listing pages first (already fetched), then as many event pages as the budget allows
        $eventPages = array_slice(array_keys(array_diff_key($events, $listing)), 0, max(0, $budget - $fetched));
        return array_merge(array_keys($listing), $eventPages);
    }

    public function fetch(string $url, array $config): string
    {
        if (isset($this->pageCache[$url])) {
            $html = $this->pageCache[$url];
            unset($this->pageCache[$url]);
            return $html;
        }
        return $this->httpGet($url);
    }

    public function getSourceMetadata(array $config): array
    {
        return [
            'platform'           => 'web',
            'acquisition_method' => 'crawler -> structured data',
            'credentials'        => [],
            'configured'         => $this->isConfigured($config),
            'limitations'        => 'Only extracts schema.org Event data (JSON-LD/microdata). Obeys robots.txt and Crawl-delay, stays on the configured site, max_pages per run. Check each site\'s terms before enabling.',
        ];
    }

    /** @return string[] absolute, de-duplicated links on the page */
    private function links(string $html, string $base): array
    {
        if (!preg_match_all('#<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1#is', $html, $m)) {
            return [];
        }
        // <base href> changes how relative links resolve
        if (preg_match('#<base\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1#is', $html, $b) && ($abs = $this->absoluteUrl($base, $b[2]))) {
            $base = $abs;
        }
        $out = [];
        foreach ($m[2] as $href) {
            if (($abs = $this->absoluteUrl($base, $href)) !== null) {
                $out[$abs] = true;
            }
        }
        return array_keys($out);
    }

    private function validRegex(mixed $rx): ?string
    {
        return is_string($rx) && $rx !== '' && @preg_match($rx, '') !== false ? $rx : null;
    }
}
