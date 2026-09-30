#!/usr/bin/env php
<?php

/**
 * Try a public source BEFORE adding it: runs the real adapter (robots.txt,
 * politeness delay, crawling, structured-data extraction, district
 * resolution) and prints what would be collected. Saves NOTHING.
 *
 *   php bin/discovery-probe.php <url> [--type=page|crawl|rss|ics|sitemap] [--pattern=REGEX] [--follow=REGEX] [--max=N]
 *
 * --type defaults to a guess from the URL/content (ics/rss/sitemap/page).
 * crawl: <url> is a listing page; event links matching --pattern are followed.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

use NEvents\Core\Application;
use NEvents\Services\Ingestion\IcsFeedAdapter;
use NEvents\Services\Ingestion\JsonLdEventAdapter;
use NEvents\Services\Ingestion\RobotsDisallowedException;
use NEvents\Services\Ingestion\RssEventAdapter;
use NEvents\Services\Ingestion\SitemapJsonLdAdapter;
use NEvents\Services\Ingestion\WebCrawlerAdapter;
use NEvents\Services\Location\DistrictService;

$app = Application::getInstance();
$app->bootstrap(BASE_PATH);

$url = null;
$opt = ['type' => null, 'pattern' => null, 'follow' => null, 'max' => 15];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--(type|pattern|follow|max)=(.*)$/s', $a, $m)) { $opt[$m[1]] = $m[2]; }
    elseif (!str_starts_with($a, '--')) { $url = $a; }
}
if (!$url || !preg_match('#^(https?|webcal)://#i', $url)) {
    fwrite(STDERR, "Usage: php bin/discovery-probe.php <url> [--type=page|crawl|rss|ics|sitemap] [--pattern=REGEX] [--follow=REGEX] [--max=N]\n");
    exit(1);
}

$type = $opt['type'] ?? match (true) {
    (bool) preg_match('#(\.ics$|^webcal:|/ical|/calendar)#i', $url) => 'ics',
    (bool) preg_match('#(rss|feed|atom)#i', $url)                    => 'rss',
    (bool) preg_match('#sitemap.*\.xml#i', $url)                     => 'sitemap',
    $opt['pattern'] !== null                                         => 'crawl',
    default                                                          => 'page',
};

[$adapter, $config] = match ($type) {
    'ics'     => [$app->get(IcsFeedAdapter::class),       ['url' => $url]],
    'rss'     => [$app->get(RssEventAdapter::class),      ['url' => $url, 'max_items' => (int) $opt['max']]],
    'sitemap' => [$app->get(SitemapJsonLdAdapter::class), ['url' => $url, 'url_pattern' => $opt['pattern'] ?? '', 'max_urls' => (int) $opt['max']]],
    'crawl'   => [$app->get(WebCrawlerAdapter::class),    array_filter(['start_urls' => [$url], 'event_pattern' => $opt['pattern'], 'follow_pattern' => $opt['follow'], 'max_pages' => (int) $opt['max']])],
    default   => [$app->get(JsonLdEventAdapter::class),   ['url' => $url]],
};

echo "Probing {$url} as '{$type}' (robots.txt respected, nothing is saved)\n\n";
$districts = $app->get(DistrictService::class);
$found = 0;
$t = microtime(true);

try {
    $items = $adapter->discover($config);
} catch (RobotsDisallowedException $e) {
    echo "✗ robots.txt disallows this URL — it cannot be used as a source.\n";
    exit(2);
} catch (Throwable $e) {
    echo "✗ " . $e->getMessage() . "\n";
    exit(2);
}
echo count($items) . " page(s)/feed(s) to read\n";

foreach ($items as $item) {
    try {
        $records = $adapter->normalize($adapter->extract($adapter->fetch($item, $config), $item, $config), 0);
    } catch (RobotsDisallowedException) {
        echo "  - skipped (robots.txt): {$item}\n";
        continue;
    } catch (Throwable $e) {
        echo "  - failed: {$item} — {$e->getMessage()}\n";
        continue;
    }
    foreach ($records as $r) {
        $found++;
        $district = null;
        foreach (['city_raw', 'region_raw', 'venue_address', 'venue_name'] as $f) {
            if (!empty($r[$f]) && ($id = $districts->resolveDistrictId((string) $r[$f]))) { $district = $districts->findById($id)['name'] ?? $id; break; }
        }
        printf("\n  • %s\n    when: %s%s   confidence: %d%s\n    where: %s  → district: %s\n    link: %s\n",
            $r['title'] ?? '(no title)',
            $r['start_datetime'] ?? '?', !empty($r['end_datetime']) ? ' – ' . $r['end_datetime'] : '',
            $r['confidence'] ?? 0, !empty($r['review_reasons']) ? ' (review: ' . implode(', ', $r['review_reasons']) . ')' : '',
            trim(($r['venue_name'] ?? '') . ' ' . ($r['venue_address'] ?? '')) ?: ($r['format_raw'] ?? '—'),
            $district ?? ($r['format_raw'] === 'online' ? 'online' : 'UNRESOLVED (would need review)'),
            $r['registration_url'] ?? $r['source_url'] ?? '—');
    }
}

printf("\n%d event candidate(s) in %.1fs.%s\n", $found, microtime(true) - $t,
    $found ? '' : ' No schema.org Event data found — this page can\'t be used as a structured source.');
