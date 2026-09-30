<?php

declare(strict_types=1);

namespace NEvents\Services\Ingestion;

use NEvents\Services\Events\RegistrationRedirectService;
use NEvents\Services\Ingestion\Web\StructuredDataExtractor;

/**
 * One public page (or a few, config_json.urls) -> schema.org Event
 * structured data (JSON-LD or microdata). Base class for the crawler,
 * sitemap and search adapters.
 */
class JsonLdEventAdapter extends AbstractSourceAdapter
{
    public function __construct(
        RegistrationRedirectService         $ssrf,
        protected StructuredDataExtractor   $structured,
    ) {
        parent::__construct($ssrf);
    }

    public function discover(array $sourceConfig): array
    {
        $urls = array_merge(
            !empty($sourceConfig['url']) ? [(string) $sourceConfig['url']] : [],
            array_map('strval', (array) ($sourceConfig['urls'] ?? []))
        );
        return array_values(array_unique(array_filter($urls)));
    }

    public function isConfigured(array $sourceConfig): bool
    {
        return !empty($sourceConfig['url']) || !empty($sourceConfig['urls']);
    }

    public function fetch(string $url, array $sourceConfig): string
    {
        return $this->httpGet($url);
    }

    public function extract(string $rawContent, string $url, array $sourceConfig): array
    {
        return ['events' => $this->structured->extract($rawContent), 'page_url' => $url];
    }

    public function normalize(array $extracted, int $sourceId): array
    {
        return $this->structured->normalize($extracted['events'] ?? [], (string) ($extracted['page_url'] ?? ''), $sourceId);
    }

    public function getSourceMetadata(array $sourceConfig): array
    {
        return [
            'platform'           => 'web',
            'acquisition_method' => 'structured data (JSON-LD / microdata)',
            'credentials'        => [],
            'configured'         => $this->isConfigured($sourceConfig),
            'limitations'        => 'Only pages that publish schema.org Event data. Obeys robots.txt. Set config_json.url (or urls[]) to pages whose terms permit automated reading.',
        ];
    }
}
