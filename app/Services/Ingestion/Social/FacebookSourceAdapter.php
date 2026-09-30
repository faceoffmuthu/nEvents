<?php

declare(strict_types=1);

namespace NEvents\Services\Ingestion\Social;

/**
 * Facebook Pages via the Graph API (Meta): GET /{page-id}/posts for Pages
 * this app holds a Page access token for (organizer-authorized), or Pages
 * readable under an approved "Page Public Content Access" feature.
 *
 * Credentials: FACEBOOK_PAGE_ACCESS_TOKEN + FACEBOOK_PAGE_IDS (or
 * config_json.accounts). There is no global Facebook event search — the
 * old public event search API is not available, and it is not assumed here.
 */
class FacebookSourceAdapter extends AbstractSocialAdapter
{
    protected function platform(): string { return 'facebook'; }

    protected function credentialNames(): array
    {
        return ['FACEBOOK_PAGE_ACCESS_TOKEN', 'FACEBOOK_PAGE_IDS (or config_json.accounts)', 'META_GRAPH_API_VERSION'];
    }

    protected function hasCredentials(): bool
    {
        return $this->social('facebook_page_token') !== '';
    }

    protected function limitations(): string
    {
        return 'Only Pages the app is authorized for (Page token) or approved Page Public Content Access. No global Facebook event search.';
    }

    protected function discoverLive(array $config): array
    {
        $ids = [];
        foreach ($this->accounts($config, 'facebook_page_ids') as $a) {
            $clean = preg_replace('/[^0-9A-Za-z._-]/', '', (string) $a['id']);
            if ($clean !== '') $ids[] = 'page:' . $clean;
        }
        return $ids;
    }

    protected function fetchLive(string $id, array $config): string
    {
        [, $pageId] = explode(':', $id, 2);
        return $this->getJson('https://graph.facebook.com/' . $this->social('meta_graph_version') . '/' . rawurlencode($pageId) . '/posts?' . http_build_query([
            'fields'       => 'id,message,created_time,permalink_url,full_picture,from',
            'limit'        => 25,
            'access_token' => $this->social('facebook_page_token'),
        ]));
    }

    protected function parsePosts(array $json, string $id, array $config): array
    {
        $posts = [];
        foreach ((array) ($json['data'] ?? []) as $p) {
            if (!is_array($p) || empty($p['id'])) continue;
            $posts[] = [
                'id'             => (string) $p['id'],
                'text'           => (string) ($p['message'] ?? ''),
                'permalink'      => (string) ($p['permalink_url'] ?? ''),
                'posted_at'      => $p['created_time'] ?? null,
                'account_handle' => $p['from']['name'] ?? null,
                'account_name'   => $p['from']['name'] ?? null,
                'image_url'      => $p['full_picture'] ?? null,
                'urls'           => [],
                'place_text'     => null,
            ];
        }
        return $posts;
    }
}
