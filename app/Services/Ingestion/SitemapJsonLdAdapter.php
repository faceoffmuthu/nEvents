<?php

declare(strict_types=1);

namespace NEvents\Services\Ingestion;

/**
 * XML sitemap -> event pages -> schema.org Event JSON-LD.
 *
 * config_json:
 *   url          Sitemap (or sitemap index) URL                     (required)
 *   url_pattern  Regex a page URL must match, e.g. "#/events?/#"    (recommended)
 *   max_urls     Pages fetched per run (default 30)
 *
 * Suits organizer, conference, college and community sites that publish a
 * sitemap plus Event structured data on each event page.
 */
class SitemapJsonLdAdapter extends JsonLdEventAdapter
{
    public function discover(array $sourceConfig): array
    {
        if (!$this->isConfigured($sourceConfig)) {
            return [];
        }
        $max     = max(1, min(200, (int) ($sourceConfig['max_urls'] ?? 30)));
        $pattern = (string) ($sourceConfig['url_pattern'] ?? '');
        $urls    = $this->readSitemap((string) $sourceConfig['url'], 0);

        if ($pattern !== '' && @preg_match($pattern, '') !== false) {
            $urls = array_values(array_filter($urls, fn($u) => preg_match($pattern, $u)));
        }
        return array_slice(array_values(array_unique($urls)), 0, $max);
    }

    public function getSourceMetadata(array $sourceConfig): array
    {
        return [
            'platform'           => 'web',
            'acquisition_method' => 'sitemap',
            'credentials'        => [],
            'configured'         => $this->isConfigured($sourceConfig),
            'limitations'        => 'Only pages with schema.org Event JSON-LD produce candidates. Respect the site\'s terms/robots.txt; set url_pattern to limit fetches to event pages.',
        ];
    }

    /** @return string[] */
    private function readSitemap(string $url, int $depth): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($this->httpGet($url), 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        if ($xml === false) {
            return [];
        }

        $urls = [];
        $root = $xml->getName();
        foreach ($xml->children() as $node) {
            $loc = trim((string) $node->loc);
            if ($loc === '' || !preg_match('#^https?://#i', $loc)) {
                continue;
            }
            if ($root === 'sitemapindex') {
                if ($depth < 1) {
                    $urls = array_merge($urls, $this->readSitemap($loc, $depth + 1));
                }
            } else {
                $urls[] = $loc;
            }
        }
        return $urls;
    }
}
