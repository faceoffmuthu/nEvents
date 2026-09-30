<?php

declare(strict_types=1);

namespace NEvents\Services\Ingestion\Social;

use NEvents\Services\Events\RegistrationRedirectService;
use NEvents\Services\Ingestion\Search\SearchQueryBuilder;

/**
 * X (Twitter) via the official API v2 recent-search endpoint:
 *   GET https://api.twitter.com/2/tweets/search/recent   (Bearer token)
 *
 * Recent search only covers the last 7 days of posts and requires an X API
 * access tier that includes search (not the free tier). Queries are
 * generated from district x category keywords (SearchQueryBuilder) and
 * capped per run (config_json.max_queries, default 5) to respect rate limits.
 *
 * Credentials: X_BEARER_TOKEN. No HTML scraping of x.com is ever performed.
 */
class XSourceAdapter extends AbstractSocialAdapter
{
    public function __construct(
        RegistrationRedirectService $ssrf,
        SocialPostExtractor         $extractor,
        private SearchQueryBuilder  $queries,
    ) {
        parent::__construct($ssrf, $extractor);
    }

    protected function platform(): string { return 'x'; }

    protected function credentialNames(): array { return ['X_BEARER_TOKEN']; }

    protected function hasCredentials(): bool
    {
        return $this->social('x_bearer_token') !== '';
    }

    protected function limitations(): string
    {
        return 'Recent search = last 7 days only; needs a paid X API tier with search access; monthly post-read caps apply.';
    }

    protected function discoverLive(array $config): array
    {
        $max = max(1, min(20, (int) ($config['max_queries'] ?? 5)));
        $out = [];
        foreach ($this->queries->build($config, $max) as $q) {
            $out[] = 'query:' . $q . ' -is:retweet -is:reply';
        }
        return $out;
    }

    protected function fetchLive(string $id, array $config): string
    {
        $query = substr($id, strlen('query:'));
        return $this->getJson('https://api.twitter.com/2/tweets/search/recent?' . http_build_query([
            'query'        => mb_substr($query, 0, 512),
            'max_results'  => 25,
            'tweet.fields' => 'created_at,author_id,entities,lang',
            'expansions'   => 'author_id',
            'user.fields'  => 'username,name',
        ]), ['Authorization: Bearer ' . $this->social('x_bearer_token')]);
    }

    protected function parsePosts(array $json, string $id, array $config): array
    {
        $users = [];
        foreach ((array) ($json['includes']['users'] ?? []) as $u) {
            if (!empty($u['id'])) $users[$u['id']] = $u;
        }
        $posts = [];
        foreach ((array) ($json['data'] ?? []) as $t) {
            if (!is_array($t) || empty($t['id'])) continue;
            $user = $users[$t['author_id'] ?? ''] ?? [];
            $handle = $user['username'] ?? null;
            $urls = [];
            foreach ((array) ($t['entities']['urls'] ?? []) as $u) {
                if (!empty($u['expanded_url'])) $urls[] = (string) $u['expanded_url'];
            }
            $posts[] = [
                'id'             => (string) $t['id'],
                'text'           => (string) ($t['text'] ?? ''),
                'permalink'      => $handle ? "https://x.com/{$handle}/status/{$t['id']}" : "https://x.com/i/web/status/{$t['id']}",
                'posted_at'      => $t['created_at'] ?? null,
                'account_handle' => $handle,
                'account_name'   => $user['name'] ?? $handle,
                'image_url'      => null,   // media reuse not permitted by default
                'urls'           => $urls,
                'place_text'     => null,
            ];
        }
        return $posts;
    }
}
