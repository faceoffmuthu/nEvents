<?php

declare(strict_types=1);

namespace NEvents\Services\Ingestion\Social;

use NEvents\Core\Application;
use NEvents\Services\Events\RegistrationRedirectService;
use NEvents\Services\Ingestion\AbstractSourceAdapter;

/**
 * Shared plumbing for approved social-platform connectors.
 *
 * Rules every subclass follows:
 *  - Only official, documented API endpoints, authenticated with credentials
 *    the platform issued for this app. No login automation, no scraping of
 *    platform HTML, no CAPTCHA handling, no private content.
 *  - Not configured (no credentials) => discover() returns [] and the admin
 *    UI shows the connector as disabled. Nothing breaks.
 *  - Every post only becomes a CANDIDATE (via SocialPostExtractor). Nothing
 *    is published from here.
 *  - Post images are only kept when config_json.image_reuse_permitted is
 *    true (i.e. the account owner / terms allow it); otherwise the event uses
 *    the category fallback artwork.
 *  - config_json.fixture (non-production only) reads a local JSON file shaped
 *    like the real API response, for tests without credentials.
 */
abstract class AbstractSocialAdapter extends AbstractSourceAdapter
{
    /** Official API endpoints, not web pages — robots.txt does not apply. */
    protected bool $respectRobots = false;

    public function __construct(
        RegistrationRedirectService   $ssrf,
        protected SocialPostExtractor $extractor,
    ) {
        parent::__construct($ssrf);
    }

    abstract protected function platform(): string;

    /** Env/config credential names this connector needs. */
    abstract protected function credentialNames(): array;

    abstract protected function hasCredentials(): bool;

    /** @return array<string> identifiers to fetch (never URLs carrying tokens) */
    abstract protected function discoverLive(array $config): array;

    abstract protected function fetchLive(string $id, array $config): string;

    /**
     * Converts a decoded API response into common post shape:
     * [{id, text, permalink, posted_at, account_handle, account_name, urls[], image_url, place_text}]
     */
    abstract protected function parsePosts(array $json, string $id, array $config): array;

    abstract protected function limitations(): string;

    public function isConfigured(array $config): bool
    {
        return $this->fixturePath($config) !== null || $this->hasCredentials();
    }

    public function discover(array $config): array
    {
        if ($path = $this->fixturePath($config)) {
            return ['fixture:' . basename($path)];
        }
        return $this->hasCredentials() ? $this->discoverLive($config) : [];
    }

    public function fetch(string $id, array $config): string
    {
        if (str_starts_with($id, 'fixture:')) {
            return (string) file_get_contents((string) $this->fixturePath($config));
        }
        return $this->fetchLive($id, $config);
    }

    public function extract(string $raw, string $id, array $config): array
    {
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            throw new \RuntimeException($this->platform() . ': unexpected API response');
        }
        if (isset($json['error'])) {
            $msg = is_array($json['error']) ? ($json['error']['message'] ?? 'API error') : (string) $json['error'];
            throw new \RuntimeException($this->platform() . ' API: ' . mb_substr($msg, 0, 200));
        }
        return ['posts' => $this->parsePosts($json, $id, $config), 'config' => $config];
    }

    public function normalize(array $extracted, int $sourceId): array
    {
        $config   = $extracted['config'] ?? [];
        $keepImg  = !empty($config['image_reuse_permitted']);
        $accounts = $this->accountDistricts($config);
        $records  = [];

        foreach ($extracted['posts'] ?? [] as $post) {
            if (empty($post['id']) || empty($post['permalink'])) {
                continue;
            }
            $post['account_district'] = $accounts[strtolower((string) ($post['account_handle'] ?? ''))] ?? null;
            if (!$keepImg) {
                $post['image_url'] = null;
            }
            $candidate = $this->extractor->analyze($post);
            $records[] = $candidate + [
                'source_id'   => $sourceId,
                'external_id' => $this->platform() . ':' . $post['id'],
                'platform'    => $this->platform(),
                'fetched_at'  => date('Y-m-d H:i:s'),
            ];
        }
        return $records;
    }

    /** Social candidates are validated by the extractor; only identity fields are required here. */
    public function validate(array $normalized): array
    {
        return empty($normalized['source_url']) ? ['source_url is required'] : [];
    }

    public function healthCheck(array $config): bool
    {
        return $this->isConfigured($config);
    }

    public function getSourceMetadata(array $config): array
    {
        return [
            'platform'           => $this->platform(),
            'acquisition_method' => $this->fixturePath($config) ? 'fixture (test data)' : 'official API',
            'credentials'        => $this->credentialNames(),
            'configured'         => $this->isConfigured($config),
            'limitations'        => $this->limitations(),
        ];
    }

    protected function social(string $key): string
    {
        return (string) Application::getInstance()->config('app.social.' . $key, '');
    }

    protected function getJson(string $url, array $headers = []): string
    {
        return $this->httpGet($url, 15, $headers);
    }

    /** config_json.accounts: [{"id": "...", "handle": "...", "district": "Chennai"}] */
    protected function accounts(array $config, string $envList): array
    {
        $accounts = [];
        foreach ((array) ($config['accounts'] ?? []) as $a) {
            if (is_array($a) && !empty($a['id'])) $accounts[] = $a;
        }
        foreach (array_filter(array_map('trim', explode(',', $this->social($envList)))) as $id) {
            $accounts[] = ['id' => $id];
        }
        return $accounts;
    }

    private function accountDistricts(array $config): array
    {
        $map = [];
        foreach ((array) ($config['accounts'] ?? []) as $a) {
            if (is_array($a) && !empty($a['handle']) && !empty($a['district'])) {
                $map[strtolower(ltrim((string) $a['handle'], '@'))] = (string) $a['district'];
            }
        }
        return $map;
    }

    private function fixturePath(array $config): ?string
    {
        if (empty($config['fixture']) || Application::getInstance()->config('app.env') === 'production') {
            return null;
        }
        $dir  = realpath(dirname(__DIR__, 4) . '/tests/fixtures');
        $path = $dir ? realpath($dir . '/' . basename((string) $config['fixture'])) : false;
        return ($path && str_starts_with($path, $dir)) ? $path : null;
    }
}
