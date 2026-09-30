<?php

declare(strict_types=1);

namespace NEvents\Services\Ingestion;

use NEvents\Services\Events\RegistrationRedirectService;
use NEvents\Services\Ingestion\Web\StructuredDataExtractor;

/**
 * RSS 2.0 / Atom feeds of event announcements.
 *
 * A feed item's <pubDate> is when the POST was published, not when the event
 * happens. So by default each item's link is followed and the linked page's
 * schema.org Event data (JSON-LD / microdata) is used — exact date, venue,
 * price. Items whose page has no structured data fall back to a
 * low-confidence candidate that always goes to review, never auto-publish.
 *
 * config_json: url or urls[], follow_item_links (default true), max_items (default 20)
 */
class RssEventAdapter extends AbstractSourceAdapter
{
    public function __construct(
        RegistrationRedirectService       $ssrf,
        private StructuredDataExtractor   $structured,
    ) {
        parent::__construct($ssrf);
    }

    public function isConfigured(array $config): bool
    {
        return !empty($config['url']) || !empty($config['urls']);
    }

    public function discover(array $config): array
    {
        $urls = array_merge(!empty($config['url']) ? [(string) $config['url']] : [], array_map('strval', (array) ($config['urls'] ?? [])));
        return array_values(array_unique(array_filter($urls)));
    }

    public function fetch(string $url, array $config): string
    {
        return $this->httpGet($url);
    }

    public function extract(string $rawContent, string $url, array $config): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($rawContent, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
        if ($xml === false) {
            throw new \RuntimeException('Not an RSS/Atom feed: ' . $this->redact($url));
        }

        $items = [];
        if (isset($xml->channel)) {                                   // RSS 2.0
            foreach ($xml->channel->item as $item) {
                $items[] = [
                    'title' => (string) $item->title, 'link' => trim((string) $item->link),
                    'description' => (string) $item->description, 'pubDate' => (string) $item->pubDate,
                    'guid' => (string) $item->guid,
                ];
            }
        } else {                                                      // Atom
            foreach ($xml->entry as $entry) {
                $link = '';
                foreach ($entry->link as $l) {
                    if ((string) ($l['rel'] ?? 'alternate') === 'alternate') { $link = (string) $l['href']; break; }
                }
                $items[] = [
                    'title' => (string) $entry->title, 'link' => trim($link),
                    'description' => (string) ($entry->summary ?: $entry->content), 'pubDate' => (string) ($entry->updated ?: $entry->published),
                    'guid' => (string) $entry->id,
                ];
            }
        }

        // Follow item links to their pages' structured event data
        if (($config['follow_item_links'] ?? true) !== false) {
            $max = max(1, (int) ($config['max_items'] ?? 20));
            foreach ($items as $i => &$item) {
                if ($i >= $max || !preg_match('#^https?://#i', $item['link'])) continue;
                try {
                    $item['page_events'] = $this->structured->extract($this->httpGet($item['link']));
                } catch (\Throwable) {
                    $item['page_events'] = [];     // robots-disallowed / unreachable: fall back to the feed item
                }
            }
            unset($item);
        }

        return ['items' => $items, 'feed_url' => $url];
    }

    public function normalize(array $extracted, int $sourceId): array
    {
        $records = [];
        foreach ($extracted['items'] ?? [] as $item) {
            if (!empty($item['page_events'])) {
                foreach ($this->structured->normalize($item['page_events'], $item['link'], $sourceId) as $r) {
                    $records[] = $r;
                }
                continue;
            }
            // Fallback: the post itself. Its date is the PUBLICATION date, so it can never auto-publish.
            $records[] = [
                'source_id'        => $sourceId,
                'external_id'      => substr(sha1($item['guid'] ?: ($item['link'] . '|' . $item['title'])), 0, 40),
                'confidence'       => 40,
                'review_reasons'   => ['date_from_feed_publication_date'],
                'source_url'       => $item['link'] ?: ($extracted['feed_url'] ?? ''),
                'title'            => trim($item['title']) ?: null,
                'description'      => trim(strip_tags($item['description'])) ?: null,
                'registration_url' => $item['link'] ?: null,
                'start_datetime'   => $this->parseDatetime($item['pubDate']),
                'fetched_at'       => date('Y-m-d H:i:s'),
            ];
        }
        return $records;
    }

    public function getSourceMetadata(array $config): array
    {
        return [
            'platform'           => 'web',
            'acquisition_method' => 'rss/atom -> item pages -> structured data',
            'credentials'        => [],
            'configured'         => $this->isConfigured($config),
            'limitations'        => 'Uses each item page\'s schema.org Event data; items without it are held for review (feed dates are publication dates). Obeys robots.txt.',
        ];
    }

    private function parseDatetime(?string $val): ?string
    {
        if (!$val) return null;
        try {
            return (new \DateTimeImmutable($val))->setTimezone(new \DateTimeZone('Asia/Kolkata'))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
