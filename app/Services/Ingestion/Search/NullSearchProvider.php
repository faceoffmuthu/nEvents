<?php

declare(strict_types=1);

namespace NEvents\Services\Ingestion\Search;

/**
 * Default provider: no general web search configured. Discovery keeps
 * working through direct source adapters, feeds, approved social APIs and
 * user submissions. To add a licensed provider, implement
 * SearchDiscoveryProviderInterface and bind it in Application::registerCoreBindings().
 */
class NullSearchProvider implements SearchDiscoveryProviderInterface
{
    public function name(): string { return 'none'; }

    public function isConfigured(): bool { return false; }

    public function search(string $query, int $limit = 10): array { return []; }
}
