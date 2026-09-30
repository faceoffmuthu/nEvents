<?php

declare(strict_types=1);

namespace NEvents\Services\Ingestion\Search;

use NEvents\Services\Events\RegistrationRedirectService;
use NEvents\Services\Ingestion\JsonLdEventAdapter;
use NEvents\Services\Ingestion\Web\StructuredDataExtractor;
use NEvents\Services\Security\UrlValidator;

/**
 * Search provider -> candidate URLs -> page fetch -> schema.org Event
 * JSON-LD extraction. A search hit without structured Event data produces
 * nothing. Candidates from here get the source's low trust level, so they
 * always land in the review queue rather than auto-publishing.
 *
 * config_json: max_queries (default 3), results_per_query (default 5),
 *              districts / keywords (see SearchQueryBuilder),
 *              exclude_domains ["example.com"]
 */
class SearchDiscoveryAdapter extends JsonLdEventAdapter
{
    public function __construct(
        RegistrationRedirectService              $ssrf,
        StructuredDataExtractor                  $structured,
        private SearchDiscoveryProviderInterface $provider,
        private SearchQueryBuilder               $queries,
    ) {
        parent::__construct($ssrf, $structured);
    }

    public function isConfigured(array $config): bool
    {
        return $this->provider->isConfigured();
    }

    public function discover(array $config): array
    {
        if (!$this->provider->isConfigured()) {
            return [];
        }
        $exclude = array_map('strtolower', (array) ($config['exclude_domains'] ?? []));
        // Search engines' own result pages are never fetched
        $exclude = array_merge($exclude, ['google.com', 'www.google.com', 'bing.com', 'www.bing.com']);

        $urls = [];
        foreach ($this->queries->build($config, max(1, (int) ($config['max_queries'] ?? 3))) as $q) {
            foreach ($this->provider->search($q, max(1, (int) ($config['results_per_query'] ?? 5))) as $hit) {
                $u = (string) ($hit['url'] ?? '');
                $host = strtolower((string) parse_url($u, PHP_URL_HOST));
                if (UrlValidator::isAcceptable($u) && !in_array($host, $exclude, true)) {
                    $urls[$u] = true;
                }
            }
        }
        return array_keys($urls);
    }

    public function normalize(array $extracted, int $sourceId): array
    {
        return array_map(function (array $r) {
            $r['review_reasons'] = array_merge($r['review_reasons'] ?? [], ['found_via_search_provider']);
            return $r;
        }, parent::normalize($extracted, $sourceId));
    }

    public function getSourceMetadata(array $config): array
    {
        return [
            'platform'           => 'search',
            'acquisition_method' => 'licensed search API -> JSON-LD',
            'credentials'        => ['SEARCH_PROVIDER + that provider\'s API key'],
            'configured'         => $this->provider->isConfigured(),
            'limitations'        => 'Provider: ' . $this->provider->name() . '. No provider is bundled; Google result pages are never scraped. Search hits are candidates only.',
        ];
    }
}
