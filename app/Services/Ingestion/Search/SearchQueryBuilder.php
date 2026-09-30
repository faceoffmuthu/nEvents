<?php

declare(strict_types=1);

namespace NEvents\Services\Ingestion\Search;

use NEvents\Core\Database\Connection;

/**
 * Builds search queries such as "AI meetup Chennai" / "cycling event Erode"
 * from district names x category keywords, for providers that allow content
 * search (licensed web search, X recent search).
 *
 * config_json (optional):
 *   districts  ["chennai","coimbatore"]  district slugs (default: featured cities' districts)
 *   keywords   ["AI meetup","startup networking"]  (default: built-in list)
 *
 * Rotates through the combinations across runs so every district/keyword
 * pair is eventually covered without exceeding per-run query caps.
 */
class SearchQueryBuilder
{
    private const DEFAULT_KEYWORDS = [
        'AI meetup', 'AI workshop', 'tech meetup', 'startup networking', 'business conference',
        'digital marketing event', 'cycling event', 'marathon', 'hackathon', 'developer conference',
    ];

    public function __construct(private Connection $db) {}

    /** @return string[] */
    public function build(array $config, int $max): array
    {
        $districts = $this->districtNames((array) ($config['districts'] ?? []));
        $keywords  = array_values(array_filter(array_map('strval', (array) ($config['keywords'] ?? self::DEFAULT_KEYWORDS))));
        if (!$districts || !$keywords) {
            return [];
        }

        $all = [];
        foreach ($districts as $d) {
            foreach ($keywords as $k) {
                $all[] = '"' . $k . '" ' . $d;
            }
        }

        // Rotate by hour so consecutive runs cover different combinations
        $offset = ((int) floor(time() / 3600) * $max) % count($all);
        return array_slice(array_merge(array_slice($all, $offset), array_slice($all, 0, $offset)), 0, $max);
    }

    private function districtNames(array $slugs): array
    {
        if ($slugs) {
            $in = implode(',', array_fill(0, count($slugs), '?'));
            $rows = $this->db->select("SELECT name FROM districts WHERE is_active = 1 AND slug IN ({$in}) ORDER BY name", array_values($slugs));
        } else {
            $rows = $this->db->select(
                "SELECT DISTINCT d.name FROM districts d JOIN cities c ON c.district_id = d.id AND c.is_featured = 1 WHERE d.is_active = 1 ORDER BY d.name"
            );
        }
        return array_column($rows, 'name');
    }
}
