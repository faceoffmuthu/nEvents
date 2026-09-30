<?php

declare(strict_types=1);

namespace NEvents\Repositories;

use NEvents\Core\Database\Connection;

class EventRepository
{
    public function __construct(private Connection $db) {}

    /**
     * Filters (all optional): city_id, district_id, state_id, category_id, category_ids[],
     * organizer_names[], format, free, date_from / date_to (IST wall-clock 'Y-m-d H:i:s' — converted
     * to UTC here, because occurrences are stored in UTC), search, exclude_demo,
     * sort ('soonest' | 'newest' | 'popular'; default = featured/trust/soonest).
     */
    public function findPublished(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        [$whereClause, $params] = $this->buildWhere($filters);
        $offset = ($page - 1) * $perPage;

        $order = match ($filters['sort'] ?? '') {
            'soonest' => 'next_start ASC',
            'newest'  => 'e.published_at DESC, next_start ASC',
            'popular' => 'e.save_count DESC, next_start ASC',
            default   => 'e.is_featured DESC, e.trust_score DESC, next_start ASC',
        };

        $sql = "
            SELECT e.*,
                   MIN(eo.start_at_utc)  AS next_start,
                   MIN(eo.end_at_utc)    AS next_end,
                   COALESCE(o.name, e.organizer_display_name) AS organizer_name,
                   o.slug                AS organizer_slug,
                   c.name                AS city_name,
                   c.slug                AS city_slug,
                   (SELECT dd.name FROM districts dd WHERE dd.id = c.district_id) AS district_name,
                   v.name                AS venue_name,
                   v.locality            AS venue_locality,
                   v.address             AS venue_address,
                   catp.slug             AS category_slug,
                   catp.name             AS category_name,
                   catp.color            AS category_color,
                   catp.icon             AS category_icon
            FROM events e
            JOIN event_occurrences eo ON eo.event_id = e.id
                 AND eo.status = 'scheduled'
                 AND eo.start_at_utc >= UTC_TIMESTAMP()
            LEFT JOIN organizers o ON o.id = e.primary_organizer_id
            LEFT JOIN cities     c ON c.id = e.city_id
            LEFT JOIN venues     v ON v.id = e.venue_id
            LEFT JOIN event_categories ecp ON ecp.event_id = e.id AND ecp.is_primary = 1
            LEFT JOIN categories catp ON catp.id = ecp.category_id
            WHERE {$whereClause}
            GROUP BY e.id
            ORDER BY {$order}
            LIMIT :limit OFFSET :offset
        ";

        $params[':limit']  = $perPage;
        $params[':offset'] = $offset;

        return $this->db->select($sql, $params);
    }

    public function countPublished(array $filters = []): int
    {
        [$whereClause, $params] = $this->buildWhere($filters);

        $sql = "
            SELECT COUNT(DISTINCT e.id)
            FROM events e
            JOIN event_occurrences eo ON eo.event_id = e.id
                 AND eo.status = 'scheduled'
                 AND eo.start_at_utc >= UTC_TIMESTAMP()
            WHERE {$whereClause}
        ";

        $row = $this->db->selectOne($sql, $params);
        return (int)array_values($row)[0];
    }

    /**
     * Cities (anywhere in India) that have upcoming published events: the Tamil Nadu
     * city, else the "City, State" place label kept on the venue for events elsewhere.
     */
    public function countCitiesWithEvents(): int
    {
        $row = $this->db->selectOne("
            SELECT COUNT(DISTINCT LOWER(TRIM(COALESCE(c.name, SUBSTRING_INDEX(v.locality, ',', 1))))) AS n
            FROM events e
            JOIN event_occurrences eo ON eo.event_id = e.id
                 AND eo.status = 'scheduled'
                 AND eo.start_at_utc >= UTC_TIMESTAMP()
            LEFT JOIN cities c ON c.id = e.city_id
            LEFT JOIN venues v ON v.id = e.venue_id
            WHERE e.status = 'published' AND e.format <> 'online'
              AND COALESCE(c.name, v.locality) IS NOT NULL
        ");
        return (int) ($row['n'] ?? 0);
    }

    /** One WHERE builder for list + count, so both always apply the same filters. */
    private function buildWhere(array $filters): array
    {
        $where  = ["e.status = 'published'"];
        $params = [];

        if (!empty($filters['city_id'])) {
            $where[]  = 'e.city_id = :city_id';
            $params[':city_id'] = $filters['city_id'];
        }
        if (!empty($filters['district_id'])) {
            $where[]  = 'EXISTS (SELECT 1 FROM cities dci WHERE dci.id = e.city_id AND dci.district_id = :district_id)';
            $params[':district_id'] = $filters['district_id'];
        }
        if (!empty($filters['state_id'])) {
            $where[]  = 'EXISTS (SELECT 1 FROM cities sci JOIN districts sdi ON sdi.id = sci.district_id WHERE sci.id = e.city_id AND sdi.state_id = :state_id)';
            $params[':state_id'] = $filters['state_id'];
        }
        if (!empty($filters['category_id'])) {
            $where[]  = 'EXISTS (SELECT 1 FROM event_categories ec WHERE ec.event_id = e.id AND ec.category_id = :cat_id)';
            $params[':cat_id'] = $filters['category_id'];
        }
        if (!empty($filters['category_ids'])) {
            $ph = [];
            foreach (array_values(array_unique(array_map('intval', (array) $filters['category_ids']))) as $i => $id) {
                $ph[] = ":cids{$i}";
                $params[":cids{$i}"] = $id;
            }
            $where[] = 'EXISTS (SELECT 1 FROM event_categories eci WHERE eci.event_id = e.id AND eci.category_id IN (' . implode(',', $ph) . '))';
        }
        if (!empty($filters['organizer_names'])) {
            // Linked organizer's name, else the name given on the event (case/space-insensitive)
            $ph = [];
            foreach (array_values(array_unique(array_map(fn ($n) => mb_strtolower(trim((string) $n)), (array) $filters['organizer_names']))) as $i => $name) {
                $ph[] = ":orgn{$i}";
                $params[":orgn{$i}"] = $name;
            }
            $where[] = 'LOWER(TRIM(COALESCE((SELECT og.name FROM organizers og WHERE og.id = e.primary_organizer_id), e.organizer_display_name))) IN (' . implode(',', $ph) . ')';
        }
        if (!empty($filters['format'])) {
            $where[]  = 'e.format = :format';
            $params[':format'] = $filters['format'];
        }
        if (!empty($filters['free'])) {
            $where[]  = "e.pricing_type = 'free'";
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'eo.start_at_utc >= :date_from';
            $params[':date_from'] = $this->istToUtc($filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'eo.start_at_utc <= :date_to';
            $params[':date_to'] = $this->istToUtc($filters['date_to']);
        }
        if (!empty($filters['search'])) {
            $where[]  = "(MATCH(e.title, e.short_summary) AGAINST(:search IN BOOLEAN MODE) OR e.title LIKE :search_like)";
            $params[':search']      = $filters['search'] . '*';
            $params[':search_like'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['exclude_demo'])) {
            $where[]  = "e.data_origin NOT IN ('demo','seed')";
        }

        return [implode(' AND ', $where), $params];
    }

    /** "AND <alias>.district_id = :district_id" when a district is chosen; nothing for All India. */
    private function districtCond(?int $districtId, string $alias, array &$params): string
    {
        if (!$districtId) {
            return '';
        }
        $params[':district_id'] = $districtId;
        return "AND {$alias}.district_id = :district_id";
    }

    /** IST wall-clock -> UTC (occurrences are stored in UTC). */
    private function istToUtc(string $local): string
    {
        return (new \DateTimeImmutable($local, new \DateTimeZone('Asia/Kolkata')))
            ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** Events a user posted (all statuses except archived), for the profile. */
    public function countByCreator(int $userId): int
    {
        $row = $this->db->selectOne(
            "SELECT COUNT(*) n FROM events WHERE created_by_user_id = :u AND status <> 'archived'",
            [':u' => $userId]
        );
        return (int) ($row['n'] ?? 0);
    }

    /** Original page of a discovered event (primary source first), or null for posted events. */
    public function getPrimarySourceUrl(int $eventId): ?string
    {
        $row = $this->db->selectOne(
            "SELECT source_url FROM event_sources WHERE event_id = :e AND source_url LIKE 'http%' ORDER BY is_primary DESC, id ASC LIMIT 1",
            [':e' => $eventId]
        );
        return $row ? (string) $row['source_url'] : null;
    }

    /** @var array<int,int[]> saved event ids per user, loaded once per request */
    private static array $savedIdsCache = [];

    /** For cards: is this event saved by the signed-in user? (one query per request) */
    public function isSavedByCurrentUser(int $eventId): bool
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId === 0) {
            return false;
        }
        self::$savedIdsCache[$userId] ??= array_flip(array_map('intval', array_column(
            $this->db->select('SELECT event_id FROM saved_events WHERE user_id = :u', [':u' => $userId]), 'event_id'
        )));
        return isset(self::$savedIdsCache[$userId][$eventId]);
    }

    public function findBySlug(string $slug): array|false
    {
        return $this->db->selectOne("
            SELECT e.*,
                   COALESCE(o.name, e.organizer_display_name) AS organizer_name,
                   o.slug  AS organizer_slug,
                   o.logo_url AS organizer_logo,
                   o.website  AS organizer_website,
                   o.description AS organizer_description,
                   c.name  AS city_name,
                   c.slug  AS city_slug,
                   v.name  AS venue_name,
                   v.address  AS venue_address,
                   v.latitude AS venue_lat,
                   v.longitude AS venue_lon,
                   v.map_url   AS venue_map_url,
                   v.locality  AS venue_locality,
                   v.postal_code AS venue_postal_code,
                   d.name      AS district_name,
                   d.slug      AS district_slug,
                   sub.name    AS submitter_name,
                   catp.slug   AS category_slug,
                   catp.name   AS category_name,
                   catp.color  AS category_color,
                   catp.icon   AS category_icon
            FROM events e
            LEFT JOIN organizers o ON o.id = e.primary_organizer_id
            LEFT JOIN cities     c ON c.id = e.city_id
            LEFT JOIN districts  d ON d.id = c.district_id
            LEFT JOIN users    sub ON sub.id = e.created_by_user_id
            LEFT JOIN venues     v ON v.id = e.venue_id
            LEFT JOIN event_categories ecp ON ecp.event_id = e.id AND ecp.is_primary = 1
            LEFT JOIN categories catp ON catp.id = ecp.category_id
            WHERE e.slug = :slug AND e.status IN ('published','postponed','cancelled','completed')
        ", [':slug' => $slug]);
    }

    public function getOccurrences(int $eventId): array
    {
        return $this->db->select("
            SELECT * FROM event_occurrences
            WHERE event_id = :id AND status = 'scheduled'
            ORDER BY start_at_utc ASC
        ", [':id' => $eventId]);
    }

    public function getCategories(int $eventId): array
    {
        return $this->db->select("
            SELECT c.*, ec.is_primary
            FROM categories c
            JOIN event_categories ec ON ec.category_id = c.id
            WHERE ec.event_id = :id
            ORDER BY ec.is_primary DESC, c.sort_order ASC
        ", [':id' => $eventId]);
    }

    public function getTags(int $eventId): array
    {
        return $this->db->select("
            SELECT t.*
            FROM tags t
            JOIN event_tags et ON et.tag_id = t.id
            WHERE et.event_id = :id
        ", [':id' => $eventId]);
    }

    public function getRelated(int $eventId, int $cityId, array $categoryIds, int $limit = 6): array
    {
        $catPlaceholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $params = array_merge([$eventId, $cityId], $categoryIds, [$eventId, $limit]);

        return $this->db->select("
            SELECT DISTINCT e.*,
                MIN(eo.start_at_utc) AS next_start,
                catp.slug  AS category_slug,
                catp.name  AS category_name,
                catp.color AS category_color,
                catp.icon  AS category_icon
            FROM events e
            JOIN event_occurrences eo ON eo.event_id = e.id AND eo.status = 'scheduled' AND eo.start_at_utc >= UTC_TIMESTAMP()
            JOIN event_categories ec ON ec.event_id = e.id
            LEFT JOIN event_categories ecp ON ecp.event_id = e.id AND ecp.is_primary = 1
            LEFT JOIN categories catp ON catp.id = ecp.category_id
            WHERE e.id <> ?
              AND e.status = 'published'
              AND e.city_id = ?
              AND ec.category_id IN ({$catPlaceholders})
              AND e.id <> ?
            GROUP BY e.id
            ORDER BY e.trust_score DESC, next_start ASC
            LIMIT ?
        ", $params);
    }

    public function incrementViews(int $eventId): void
    {
        $this->db->update('UPDATE events SET view_count = view_count + 1 WHERE id = :id', [':id' => $eventId]);
    }

    /** $districtId null = anywhere in India */
    public function getFeatured(?int $districtId, int $limit = 8): array
    {
        $params = [];
        return $this->db->select("
            SELECT e.*,
                   MIN(eo.start_at_utc) AS next_start,
                   COALESCE(o.name, e.organizer_display_name) AS organizer_name,
                   c.name AS city_name,
                   catp.slug  AS category_slug,
                   catp.name  AS category_name,
                   catp.color AS category_color,
                   catp.icon  AS category_icon
            FROM events e
            JOIN event_occurrences eo ON eo.event_id = e.id AND eo.status = 'scheduled' AND eo.start_at_utc >= UTC_TIMESTAMP()
            LEFT JOIN cities c ON c.id = e.city_id
            LEFT JOIN organizers o ON o.id = e.primary_organizer_id
            LEFT JOIN event_categories ecp ON ecp.event_id = e.id AND ecp.is_primary = 1
            LEFT JOIN categories catp ON catp.id = ecp.category_id
            WHERE e.status = 'published' {$this->districtCond($districtId, 'c', $params)}
            GROUP BY e.id
            ORDER BY e.is_featured DESC, e.trust_score DESC, next_start ASC
            LIMIT :limit
        ", $params + [':limit' => $limit]);
    }

    public function getUpcomingToday(?int $districtId): array
    {
        $params = [];
        // CONVERT_TZ() silently returns NULL unless MySQL's timezone tables have been
        // loaded (mysql_tzinfo_to_sql) — not true on a stock XAMPP/MySQL install. Asia/Kolkata
        // has a fixed UTC+5:30 offset with no DST, so a literal INTERVAL is portable and reliable.
        return $this->db->select("
            SELECT e.*,
                   eo.start_at_utc AS next_start,
                   COALESCE(o.name, e.organizer_display_name) AS organizer_name,
                   catp.slug  AS category_slug,
                   catp.name  AS category_name,
                   catp.color AS category_color,
                   catp.icon  AS category_icon
            FROM events e
            JOIN event_occurrences eo ON eo.event_id = e.id AND eo.status = 'scheduled'
            LEFT JOIN cities dc ON dc.id = e.city_id
            LEFT JOIN organizers o ON o.id = e.primary_organizer_id
            LEFT JOIN event_categories ecp ON ecp.event_id = e.id AND ecp.is_primary = 1
            LEFT JOIN categories catp ON catp.id = ecp.category_id
            WHERE e.status = 'published' {$this->districtCond($districtId, 'dc', $params)}
              AND DATE(DATE_ADD(eo.start_at_utc, INTERVAL 330 MINUTE)) = DATE(DATE_ADD(UTC_TIMESTAMP(), INTERVAL 330 MINUTE))
            ORDER BY eo.start_at_utc ASC
            LIMIT 10
        ", $params);
    }

    public function getThisWeekend(?int $districtId): array
    {
        $params = [];
        // See getUpcomingToday() — fixed +330 minute offset replaces CONVERT_TZ() for portability.
        return $this->db->select("
            SELECT e.*,
                   MIN(eo.start_at_utc) AS next_start,
                   COALESCE(o.name, e.organizer_display_name) AS organizer_name,
                   catp.slug  AS category_slug,
                   catp.name  AS category_name,
                   catp.color AS category_color,
                   catp.icon  AS category_icon
            FROM events e
            JOIN event_occurrences eo ON eo.event_id = e.id AND eo.status = 'scheduled'
            LEFT JOIN cities dc ON dc.id = e.city_id
            LEFT JOIN organizers o ON o.id = e.primary_organizer_id
            LEFT JOIN event_categories ecp ON ecp.event_id = e.id AND ecp.is_primary = 1
            LEFT JOIN categories catp ON catp.id = ecp.category_id
            WHERE e.status = 'published' {$this->districtCond($districtId, 'dc', $params)}
              AND DAYOFWEEK(DATE_ADD(eo.start_at_utc, INTERVAL 330 MINUTE)) IN (1,7)
              AND YEARWEEK(DATE_ADD(eo.start_at_utc, INTERVAL 330 MINUTE), 1) = YEARWEEK(DATE_ADD(UTC_TIMESTAMP(), INTERVAL 330 MINUTE), 1)
            GROUP BY e.id
            ORDER BY e.trust_score DESC, next_start ASC
            LIMIT 8
        ", $params);
    }

    public function isEventSavedByUser(int $userId, int $eventId): bool
    {
        $row = $this->db->selectOne(
            'SELECT 1 FROM saved_events WHERE user_id = :u AND event_id = :e',
            [':u' => $userId, ':e' => $eventId]
        );
        return $row !== false;
    }

    public function saveEvent(int $userId, int $eventId): void
    {
        $this->db->statement(
            'INSERT IGNORE INTO saved_events (user_id, event_id) VALUES (:u, :e)',
            [':u' => $userId, ':e' => $eventId]
        );
        $this->recountSaves($eventId);
    }

    public function unsaveEvent(int $userId, int $eventId): void
    {
        $this->db->delete(
            'DELETE FROM saved_events WHERE user_id = :u AND event_id = :e',
            [':u' => $userId, ':e' => $eventId]
        );
        $this->recountSaves($eventId);
    }

    private function recountSaves(int $eventId): void
    {
        $this->db->update(
            'UPDATE events SET save_count = (SELECT COUNT(*) FROM saved_events WHERE event_id = :e) WHERE id = :id',
            [':e' => $eventId, ':id' => $eventId]
        );
    }

    public function getSavedEvents(int $userId): array
    {
        return $this->db->select("
            SELECT e.*,
                   MIN(eo.start_at_utc) AS next_start,
                   COALESCE(o.name, e.organizer_display_name) AS organizer_name,
                   se.created_at AS saved_at,
                   catp.slug  AS category_slug,
                   catp.name  AS category_name,
                   catp.color AS category_color,
                   catp.icon  AS category_icon
            FROM saved_events se
            JOIN events e ON e.id = se.event_id
            LEFT JOIN event_occurrences eo ON eo.event_id = e.id AND eo.status = 'scheduled' AND eo.start_at_utc >= UTC_TIMESTAMP()
            LEFT JOIN organizers o ON o.id = e.primary_organizer_id
            LEFT JOIN event_categories ecp ON ecp.event_id = e.id AND ecp.is_primary = 1
            LEFT JOIN categories catp ON catp.id = ecp.category_id
            WHERE se.user_id = :uid
            GROUP BY e.id
            ORDER BY se.created_at DESC
        ", [':uid' => $userId]);
    }

    public function recordInteraction(int|null $userId, int $eventId, string $action, string $sessionId, string $ip): void
    {
        $this->db->insert(
            'INSERT INTO event_interactions (user_id, event_id, action, session_id, ip_address) VALUES (:u, :e, :a, :s, :ip)',
            [':u' => $userId, ':e' => $eventId, ':a' => $action, ':s' => $sessionId, ':ip' => $ip]
        );
    }

    public function getRegistrationUrl(int $eventId): string|null
    {
        $row = $this->db->selectOne(
            "SELECT registration_url FROM events WHERE id = :id AND status IN ('published','postponed')",
            [':id' => $eventId]
        );
        return $row ? $row['registration_url'] : null;
    }

    public function incrementClickCount(int $eventId): void
    {
        $this->db->statement(
            'UPDATE events SET click_count = click_count + 1 WHERE id = :id',
            [':id' => $eventId]
        );
    }
}
