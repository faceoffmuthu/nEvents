<?php

declare(strict_types=1);

namespace NEvents\Services\Location;

use NEvents\Core\Database\Connection;
use NEvents\Repositories\DistrictRepository;

/**
 * Canonical location lookup + normalization service for all of India (states /
 * union territories -> districts -> cities). Every part of the app that needs
 * "the list of districts" or "which district does this venue/address belong
 * to" goes through here — never a hardcoded list, never a substring guess.
 */
class DistrictService
{
    /** Other spellings of state names seen on event pages */
    private const STATE_ALIASES = [
        'tamilnadu' => 'tamil-nadu', 'tn' => 'tamil-nadu', 'orissa' => 'odisha', 'pondicherry' => 'puducherry',
        'uttaranchal' => 'uttarakhand', 'nct of delhi' => 'delhi', 'new delhi' => 'delhi', 'j&k' => 'jammu-and-kashmir',
        'andaman' => 'andaman-and-nicobar-islands', 'bengal' => 'west-bengal', 'ap' => 'andhra-pradesh',
    ];

    public function __construct(
        private DistrictRepository $districts,
        private Connection         $db,
    ) {}

    /** Every active district with its state name, grouped by state (alphabetical). */
    public function allWithState(): array
    {
        return $this->districts->allWithState();
    }

    /** Active states and union territories, alphabetical. */
    public function getStates(): array
    {
        return $this->districts->states();
    }

    public function getDistrictsByState(int $stateId): array
    {
        return $this->districts->byState($stateId);
    }

    public function findById(int $id): array|false
    {
        return $this->districts->findActiveById($id);
    }

    public function findBySlug(string $slug): array|false
    {
        return $this->districts->findBySlug($slug);
    }

    public function isValidActiveDistrict(int $id): bool
    {
        return $this->districts->findActiveById($id) !== false;
    }

    /**
     * The district the visitor is browsing, or null for "All India":
     * signed-in preference -> this visit's choice -> All India.
     *
     * @return array{id:int, slug:string, name:string, state_name:string}|null
     */
    public function activeDistrict(?int $userId): ?array
    {
        $id = 0;
        if ($userId) {
            $row = $this->db->selectOne(
                "SELECT COALESCE(ul.district_id, c.district_id) AS did
                   FROM user_locations ul LEFT JOIN cities c ON c.id = ul.city_id
                  WHERE ul.user_id = :u AND ul.is_primary = 1 LIMIT 1",
                [':u' => $userId]
            );
            $id = (int) ($row['did'] ?? 0);
        }
        $id = $id ?: (int) ($_SESSION['district_id'] ?? 0);
        $d  = $id ? $this->districts->findActiveById($id) : false;

        return $d ? ['id' => (int) $d['id'], 'slug' => $d['slug'], 'name' => $d['name'], 'state_name' => $d['state_name']] : null;
    }

    /**
     * Resolves free-text location/venue information to a district ID.
     * Tries the whole text, then each comma-separated part: district names and
     * alternate names (Trichy, Bangalore, Pondicherry) first, then city and
     * locality names (Secunderabad, Velachery). $context (e.g. the whole
     * address) is searched for a state name, which settles names that exist
     * in more than one state (Bilaspur, Hamirpur, Udaipur, Dwarka).
     *
     * Returns null when nothing matches confidently — including a name that
     * could be several districts — so callers leave the district unset and
     * flag the event for review rather than guess.
     */
    public function resolveDistrictId(string $locationText, string $context = ''): ?int
    {
        $text = trim($locationText);
        if ($text === '') {
            return null;
        }
        $stateId = $this->stateHint($context !== '' ? $context : $text);

        $whole = $this->normalize($text);
        $id = $this->matchNormalized($whole, $stateId);
        if ($id !== null) {
            return $id;
        }

        foreach (preg_split('/[,\n\/|]/', $text) as $part) {
            $partNorm = $this->normalize($part);
            if ($partNorm === '' || $partNorm === $whole) {
                continue;
            }
            $id = $this->matchNormalized($partNorm, $stateId);
            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Location-picker search (case-insensitive, matches the start of any word):
     * districts, other names for them, cities / towns, neighbourhoods, and — when a
     * state or union territory is typed — its districts. Best matches first.
     *
     * @return list<array{district_id:int, label:string, detail:string}>
     */
    public function searchPlaces(string $q, int $limit = 12): array
    {
        $params = [':p' => $q . '%', ':w' => '% ' . $q . '%'];
        $rows = $this->db->select(
            "SELECT d.id AS district_id, d.name AS label, s.name AS detail, 0 AS kind
               FROM districts d JOIN states s ON s.id = d.state_id
              WHERE d.is_active = 1 AND (LOWER(d.name) LIKE :p OR LOWER(d.name) LIKE :w)
             UNION ALL
             SELECT d.id, da.alias, CONCAT(d.name, ', ', s.name), 1
               FROM district_aliases da JOIN districts d ON d.id = da.district_id JOIN states s ON s.id = d.state_id
              WHERE d.is_active = 1 AND (da.alias_norm LIKE :p2 OR da.alias_norm LIKE :w2)
             UNION ALL
             SELECT d.id, c.name, CONCAT(d.name, ', ', s.name), 2
               FROM cities c JOIN districts d ON d.id = c.district_id JOIN states s ON s.id = d.state_id
              WHERE c.is_active = 1 AND d.is_active = 1 AND LOWER(c.name) <> LOWER(d.name)
                AND (LOWER(c.name) LIKE :p3 OR LOWER(c.name) LIKE :w3)
             UNION ALL
             SELECT d.id, a.name, CONCAT(c.name, ', ', s.name), 2
               FROM areas a JOIN cities c ON c.id = a.city_id JOIN districts d ON d.id = c.district_id JOIN states s ON s.id = d.state_id
              WHERE a.is_active = 1 AND d.is_active = 1 AND (LOWER(a.name) LIKE :p5 OR LOWER(a.name) LIKE :w5)
             UNION ALL
             SELECT d.id, d.name, s.name, 3
               FROM states s JOIN districts d ON d.state_id = s.id
              WHERE s.is_active = 1 AND d.is_active = 1 AND (LOWER(s.name) LIKE :p4 OR LOWER(s.name) LIKE :w4)",
            $params + [':p2' => $params[':p'], ':w2' => $params[':w'], ':p3' => $params[':p'], ':w3' => $params[':w'],
                       ':p4' => $params[':p'], ':w4' => $params[':w'], ':p5' => $params[':p'], ':w5' => $params[':w']]
        );

        // Exact name, then names starting with the text, then word matches; a typed
        // state lists all its districts (up to 80) after any direct matches.
        $rank = fn ($r) => [
            mb_strtolower($r['label']) === $q ? 0 : (str_starts_with(mb_strtolower($r['label']), $q) ? 1 : 2),
            (int) $r['kind'] === 3 ? 1 : 0, (int) $r['kind'], $r['label'],
        ];
        usort($rows, fn ($a, $b) => $rank($a) <=> $rank($b));

        $out = $seen = [];
        $stateTyped = false;
        foreach ($rows as $r) {
            $key = $r['district_id'] . '|' . mb_strtolower($r['label']);
            if (isset($seen[$key])) continue;
            $stateTyped = $stateTyped || (int) $r['kind'] === 3;
            if (count($out) >= ($stateTyped ? 80 : $limit)) break;
            $seen[$key] = true;
            $out[] = ['district_id' => (int) $r['district_id'], 'label' => $r['label'], 'detail' => $r['detail']];
        }
        return $out;
    }

    /** The city (or place) in a district named in $text, else the district's main city. */
    public function cityIdFor(int $districtId, string $text = ''): ?int
    {
        foreach (preg_split('/[,\n\/|]/', $text) as $part) {
            $n = $this->normalize($part);
            if ($n === '') {
                continue;
            }
            $row = $this->db->selectOne(
                "SELECT id FROM cities WHERE district_id = :d AND LOWER(name) = :n AND is_active = 1
                 UNION
                 SELECT c.id FROM areas a JOIN cities c ON c.id = a.city_id
                  WHERE c.district_id = :d2 AND LOWER(a.name) = :n2 AND a.is_active = 1
                 LIMIT 1",
                [':d' => $districtId, ':n' => $n, ':d2' => $districtId, ':n2' => $n]
            );
            if ($row) {
                return (int) $row['id'];
            }
        }
        $row = $this->db->selectOne(
            "SELECT id FROM cities WHERE district_id = :d AND is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 1",
            [':d' => $districtId]
        );
        return $row ? (int) $row['id'] : null;
    }

    /** The one state named in $text, or null (none, or more than one). */
    private function stateHint(string $text): ?int
    {
        $t = ' ' . preg_replace('/[^a-z0-9&]+/', ' ', strtolower($text)) . ' ';
        $found = [];
        foreach ($this->districts->states() as $s) {
            if (str_contains($t, ' ' . preg_replace('/[^a-z0-9&]+/', ' ', strtolower($s['name'])) . ' ')) {
                $found[$s['slug']] = (int) $s['id'];
            }
        }
        foreach (self::STATE_ALIASES as $alias => $slug) {
            if (!isset($found[$slug]) && str_contains($t, " {$alias} ")) {
                $row = $this->db->selectOne("SELECT id FROM states WHERE slug = :s", [':s' => $slug]);
                if ($row) $found[$slug] = (int) $row['id'];
            }
        }
        // "New Delhi, Delhi" and "Puducherry, Puducherry" name one state twice — fine; two different states is no hint
        return count(array_unique($found)) === 1 ? (int) reset($found) : null;
    }

    /**
     * District names + alternate names first; only if none match, city and locality
     * names. A tier matching one district wins; several (after the state hint) is ambiguous.
     */
    private function matchNormalized(string $norm, ?int $stateId): ?int
    {
        $tiers = [
            "SELECT d.id, d.state_id FROM districts d WHERE LOWER(d.name) = :n AND d.is_active = 1
             UNION
             SELECT d.id, d.state_id FROM district_aliases da JOIN districts d ON d.id = da.district_id
              WHERE da.alias_norm = :n2 AND d.is_active = 1",
            "SELECT d.id, d.state_id FROM cities c JOIN districts d ON d.id = c.district_id
              WHERE LOWER(c.name) = :n AND c.is_active = 1 AND d.is_active = 1
             UNION
             SELECT d.id, d.state_id FROM areas a JOIN cities c ON c.id = a.city_id JOIN districts d ON d.id = c.district_id
              WHERE LOWER(a.name) = :n2 AND a.is_active = 1 AND d.is_active = 1",
        ];
        foreach ($tiers as $sql) {
            $rows = $this->db->select($sql, [':n' => $norm, ':n2' => $norm]);
            if ($stateId !== null) {
                $rows = array_filter($rows, fn ($r) => (int) $r['state_id'] === $stateId);
            }
            $ids = array_unique(array_map(fn ($r) => (int) $r['id'], $rows));
            if (count($ids) === 1) {
                return reset($ids);
            }
            if (count($ids) > 1) {
                return null;
            }
        }
        return null;
    }

    private function normalize(string $s): string
    {
        $s = preg_replace('/\b\d{3}\s?\d{3}\b/', '', trim($s));             // PIN code
        $s = preg_replace('/\b(dt\.?|dist\.?|district)$/i', '', trim((string) $s));
        $s = preg_replace('/\s+/', ' ', (string) $s);
        return strtolower(trim((string) $s, " \t-."));
    }
}
