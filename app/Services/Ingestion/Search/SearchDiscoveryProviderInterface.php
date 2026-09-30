<?php

declare(strict_types=1);

namespace NEvents\Services\Ingestion\Search;

/**
 * An approved / licensed web-search API (never scraped result pages).
 * Search results are CANDIDATE URLS only: SearchDiscoveryAdapter fetches
 * each page and keeps it only if it carries schema.org Event data, and the
 * result still goes through validation, location, duplicate and trust checks.
 */
interface SearchDiscoveryProviderInterface
{
    public function name(): string;

    /** False when no API key/endpoint is configured — discovery then skips search entirely. */
    public function isConfigured(): bool;

    /**
     * @return array<int, array{url:string, title?:string, snippet?:string}>
     */
    public function search(string $query, int $limit = 10): array;
}
