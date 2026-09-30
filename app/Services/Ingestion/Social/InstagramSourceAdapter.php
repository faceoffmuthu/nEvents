<?php

declare(strict_types=1);

namespace NEvents\Services\Ingestion\Social;

/**
 * Instagram via the Instagram Graph API (Meta). Works ONLY for:
 *
 *  1. Organizer-authorized Instagram professional accounts the app has an
 *     access token for:   GET /{ig-user-id}/media
 *  2. Hashtag search, which Meta gates behind app review ("Instagram Public
 *     Content Access"):   GET /ig_hashtag_search -> GET /{hashtag-id}/recent_media
 *     Enabled only when config_json.hashtags AND config_json.hashtag_user_id are set.
 *
 * Credentials: INSTAGRAM_ACCESS_TOKEN (+ INSTAGRAM_ACCOUNT_IDS or
 * config_json.accounts). There is no unauthenticated/global Instagram search,
 * and this connector never logs in, scrapes, or reads private accounts.
 */
class InstagramSourceAdapter extends AbstractSocialAdapter
{
    private const FIELDS = 'id,caption,media_type,media_url,permalink,timestamp,username';

    protected function platform(): string { return 'instagram'; }

    protected function credentialNames(): array
    {
        return ['INSTAGRAM_ACCESS_TOKEN', 'INSTAGRAM_ACCOUNT_IDS (or config_json.accounts)', 'META_GRAPH_API_VERSION'];
    }

    protected function hasCredentials(): bool
    {
        return $this->social('instagram_access_token') !== '';
    }

    protected function limitations(): string
    {
        return 'Only organizer-authorized professional accounts (token issued to this app). Hashtag discovery needs Meta app review approval. No global public-post search exists.';
    }

    protected function discoverLive(array $config): array
    {
        $ids = [];
        foreach ($this->accounts($config, 'instagram_account_ids') as $a) {
            $ids[] = 'account:' . preg_replace('/\D/', '', (string) $a['id']);
        }
        if (!empty($config['hashtag_user_id'])) {
            foreach (array_slice((array) ($config['hashtags'] ?? []), 0, 10) as $tag) {   // Meta allows 30 unique hashtags / 7 days
                $ids[] = 'hashtag:' . preg_replace('/[^\p{L}\p{N}_]/u', '', (string) $tag);
            }
        }
        return array_values(array_filter($ids, fn($i) => !str_ends_with($i, ':')));
    }

    protected function fetchLive(string $id, array $config): string
    {
        $base  = 'https://graph.facebook.com/' . $this->social('meta_graph_version');
        $token = $this->social('instagram_access_token');
        [$kind, $value] = explode(':', $id, 2);

        if ($kind === 'account') {
            return $this->getJson($base . '/' . rawurlencode($value) . '/media?' . http_build_query([
                'fields' => self::FIELDS, 'limit' => 25, 'access_token' => $token,
            ]));
        }

        $userId = preg_replace('/\D/', '', (string) $config['hashtag_user_id']);
        $search = json_decode($this->getJson($base . '/ig_hashtag_search?' . http_build_query([
            'user_id' => $userId, 'q' => $value, 'access_token' => $token,
        ])), true);
        $hashtagId = $search['data'][0]['id'] ?? null;
        if (!$hashtagId) {
            return json_encode(['data' => []]);
        }
        return $this->getJson($base . '/' . rawurlencode((string) $hashtagId) . '/recent_media?' . http_build_query([
            'user_id' => $userId, 'fields' => 'id,caption,media_type,media_url,permalink,timestamp', 'limit' => 25, 'access_token' => $token,
        ]));
    }

    protected function parsePosts(array $json, string $id, array $config): array
    {
        $posts = [];
        foreach ((array) ($json['data'] ?? []) as $m) {
            if (!is_array($m) || empty($m['id'])) continue;
            $posts[] = [
                'id'             => (string) $m['id'],
                'text'           => (string) ($m['caption'] ?? ''),
                'permalink'      => (string) ($m['permalink'] ?? ''),
                'posted_at'      => $m['timestamp'] ?? null,
                'account_handle' => $m['username'] ?? null,
                'account_name'   => $m['username'] ?? null,
                'image_url'      => ($m['media_type'] ?? '') === 'IMAGE' ? ($m['media_url'] ?? null) : null,
                'urls'           => [],
                'place_text'     => null,
            ];
        }
        return $posts;
    }
}
