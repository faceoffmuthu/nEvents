<?php

declare(strict_types=1);

namespace NEvents\Services\Events;

use NEvents\Core\Application;
use NEvents\Core\Database\Connection;
use NEvents\Core\Log;
use NEvents\Services\Notifications\NotificationService;

/**
 * Updates an existing canonical event from a newly discovered candidate
 * that was matched to it (same source seen again with changed content, or
 * another source describing the same event).
 *
 * Trust-ranked merge — every event remembers how trustworthy its current
 * details are (events.content_score = avg(source trust, extraction
 * confidence) of the source that last set them):
 *
 *   incoming score >= current score   update every field that differs
 *                                     (date/time, venue, district, registration
 *                                     link, price, description if richer), and
 *                                     record the new provenance
 *   incoming score <  current score   only FILL fields that are empty; a
 *                                     conflicting value is logged in
 *                                     event_conflicts for an admin, not applied
 *   candidate with review flags       (vague date, low confidence) never
 *                                     overwrites — it can only fill blanks
 *
 * Events a person posted (user / organizer / admin) are never overwritten:
 * differences are logged and the event is flagged for moderators.
 *
 * Date/venue/link changes on a published event cancel queued reminders and
 * email everyone who saved it. A held (needs_review) discovered event is
 * published once a trusted, confident source confirms it.
 */
class EventMergeService
{
    private const PROTECTED_ORIGINS = ['user_submitted', 'organizer_submitted', 'admin_created', 'demo', 'seed'];

    public function __construct(
        private Connection          $db,
        private NotificationService $notifications,
    ) {}

    /**
     * @param array $record  normalized candidate (see SourceAdapterInterface::normalize)
     * @param array $ctx     source_id, trust, confidence, start_utc, end_utc, district_id, city_id, format
     * @return string[] human-readable list of what changed / was logged
     */
    public function merge(int $eventId, array $record, array $ctx): array
    {
        $event = $this->db->selectOne(
            "SELECT e.*, v.name AS venue_name_cur, v.address AS venue_address_cur,
                    (SELECT c.district_id FROM cities c WHERE c.id = e.city_id) AS district_cur,
                    (SELECT COUNT(*) FROM events e2 WHERE e2.venue_id = e.venue_id) AS venue_users
               FROM events e LEFT JOIN venues v ON v.id = e.venue_id WHERE e.id = :id",
            [':id' => $eventId]
        );
        if (!$event) {
            return [];
        }
        $occ = $this->db->selectOne(
            "SELECT * FROM event_occurrences WHERE event_id = :id ORDER BY start_at_utc ASC LIMIT 1",
            [':id' => $eventId]
        );

        $reviewFlags = (array) ($record['review_reasons'] ?? []);
        $incoming = (int) round(((int) $ctx['trust'] + (int) $ctx['confidence']) / 2);
        if ($reviewFlags) {
            $incoming = min($incoming, 59);      // uncertain data can fill gaps, never overwrite
        }
        $current = (int) ($event['content_score'] ?? $event['trust_score'] ?? 50);

        // field => [current value, incoming value, meaningful(affects attending)?]
        $fields = [
            'start'            => [$occ['start_at_utc'] ?? null, $ctx['start_utc'] ?? null, true],
            'end'              => [$occ['end_at_utc'] ?? null, $ctx['end_utc'] ?? null, true],
            'venue'            => [$event['venue_name_cur'], $this->clean($record['venue_name'] ?? null), true],
            'address'          => [$event['venue_address_cur'], $this->clean($record['venue_address'] ?? null), false],
            'district'         => [$event['district_cur'], $ctx['district_id'] ?? null, true],
            'registration_url' => [$event['registration_url'], $this->clean($record['registration_url'] ?? null), true],
            'price'            => [$event['min_price'] !== null ? (float) $event['min_price'] : null, isset($record['price_from']) ? (float) $record['price_from'] : null, false],
            'pricing_type'     => [$event['pricing_type'] === 'unknown' ? null : $event['pricing_type'], !empty($record['is_free']) ? 'free' : (!empty($record['price_from']) ? 'paid' : null), false],
            'description'      => [$event['description'], $this->clean($record['description'] ?? null), false],
            'organizer'        => [$event['organizer_display_name'], $this->clean($record['organizer_name'] ?? null), false],
            'image'            => [$event['featured_image_url'], $this->clean($record['image_url'] ?? null), false],
        ];

        $diffs = [];
        foreach ($fields as $name => [$cur, $new, $meaningful]) {
            if ($new === null || $new === '') continue;
            if ($this->same($name, $cur, $new)) continue;
            $diffs[$name] = ['cur' => $cur, 'new' => $new, 'meaningful' => $meaningful, 'empty' => $cur === null || $cur === ''];
        }
        if (!$diffs) {
            $this->touchVerified($eventId, $ctx, $incoming >= $current ? $incoming : null);
            return [];
        }

        // ── Events a person posted: never overwritten, differences flagged ──
        if (in_array($event['data_origin'], self::PROTECTED_ORIGINS, true) || $event['created_by_user_id']) {
            $logged = [];
            foreach ($diffs as $name => $d) {
                if ($d['meaningful'] && !$d['empty'] && $this->logConflict($event, $name, $d['cur'], $d['new'], (int) $ctx['source_id'])) {
                    $logged[] = $name;
                }
            }
            if ($logged && $event['moderation_status'] === 'clean') {
                $this->db->update(
                    "UPDATE events SET moderation_status = 'flagged', moderation_reason = :r WHERE id = :id",
                    [':r' => 'external source differs: ' . implode(', ', $logged), ':id' => $eventId]
                );
            }
            return $logged ? ['flagged (external source differs): ' . implode(', ', $logged)] : [];
        }

        // ── Discovered events: trust-ranked merge ──
        $overwrite = $incoming >= $current;
        $apply = [];
        $conflicts = [];
        foreach ($diffs as $name => $d) {
            if ($d['empty']) {
                $apply[$name] = $d['new'];                          // filling a blank is always fine
            } elseif ($name === 'image') {
                continue;                                           // never swap an existing image
            } elseif ($name === 'description') {
                if ($overwrite && mb_strlen((string) $d['new']) > mb_strlen((string) $d['cur']) + 40) {
                    $apply[$name] = $d['new'];                      // richer description from an equal/better source
                }
            } elseif ($overwrite) {
                $apply[$name] = $d['new'];
            } elseif ($d['meaningful'] && $this->logConflict($event, $name, $d['cur'], $d['new'], (int) $ctx['source_id'])) {
                $conflicts[] = $name;
            }
        }

        $changes = $this->apply($event, $occ, $apply, $ctx);
        $this->touchVerified($eventId, $ctx, $overwrite ? $incoming : null);

        $meaningfulChanged = array_values(array_intersect(array_keys($apply), ['start', 'end', 'venue', 'district', 'registration_url']));
        $meaningfulChanged = array_values(array_filter($meaningfulChanged, fn($f) => !$diffs[$f]['empty']));
        if ($meaningfulChanged && $event['status'] === 'published') {
            $labels = ['start' => 'date/time', 'end' => 'end time', 'venue' => 'venue', 'district' => 'location', 'registration_url' => 'registration link'];
            $this->notifications->cancelQueuedForEvent($eventId);
            $this->notifications->notifySaversOfChange($eventId, 'updated',
                'The organizer updated the ' . implode(', ', array_unique(array_map(fn($f) => $labels[$f], $meaningfulChanged))) . '.');
        }

        // A held candidate confirmed by a trusted, confident source goes live
        if ($event['status'] === 'pending' && $event['moderation_status'] === 'needs_review'
            && !str_starts_with((string) $event['moderation_reason'], 'moderator')
            && !str_contains((string) $event['moderation_reason'], 'possible_duplicate')
            && !$reviewFlags
            && (int) $ctx['confidence'] >= (int) Application::getInstance()->config('app.discovery.auto_publish_min_confidence', 80)
            && (int) $ctx['trust'] >= (int) Application::getInstance()->config('app.discovery.auto_publish_min_trust', 70)) {
            $this->db->update(
                "UPDATE events SET status = 'published', moderation_status = 'clean', moderation_reason = NULL,
                        published_at = NOW(), first_published_at = COALESCE(first_published_at, NOW()) WHERE id = :id",
                [':id' => $eventId]
            );
            $changes[] = 'published (confirmed by trusted source)';
        }

        if ($conflicts) {
            $changes[] = 'conflicts logged: ' . implode(', ', $conflicts);
        }
        if ($changes) {
            Log::get()->info('event_merged', ['event_id' => $eventId, 'source_id' => $ctx['source_id'], 'changes' => $changes]);
        }
        return $changes;
    }

    private function apply(array $event, array|false $occ, array $apply, array $ctx): array
    {
        $changes = [];
        $set = [];
        $params = [':id' => $event['id']];

        if (isset($apply['start']) || isset($apply['end'])) {
            if ($occ) {
                $this->db->update(
                    "UPDATE event_occurrences SET start_at_utc = :s, end_at_utc = :e WHERE id = :id",
                    [':s' => $apply['start'] ?? $occ['start_at_utc'], ':e' => $apply['end'] ?? $occ['end_at_utc'], ':id' => $occ['id']]
                );
            } elseif (isset($apply['start'])) {
                $this->db->insert(
                    "INSERT INTO event_occurrences (event_id, start_at_utc, end_at_utc, timezone, status) VALUES (:e, :s, :end, 'Asia/Kolkata', 'scheduled')",
                    [':e' => $event['id'], ':s' => $apply['start'], ':end' => $apply['end'] ?? null]
                );
            }
            if (isset($apply['start'])) $changes[] = 'date/time';
            if (isset($apply['end']) && !isset($apply['start'])) $changes[] = 'end time';
        }

        if (isset($apply['venue']) || isset($apply['address']) || isset($apply['district'])) {
            $venueName = $apply['venue'] ?? $event['venue_name_cur'];
            $address   = $apply['address'] ?? $event['venue_address_cur'];
            $cityId    = isset($apply['district']) ? ($ctx['city_id'] ?? $event['city_id']) : $event['city_id'];
            $districtId = $ctx['district_id'] ?? null;
            if ($event['venue_id'] && (int) $event['venue_users'] <= 1) {
                $this->db->update(
                    "UPDATE venues SET name = :n, address = :a, city_id = :c, district_id = COALESCE(:d, district_id) WHERE id = :id",
                    [':n' => mb_substr((string) ($venueName ?: $address), 0, 200), ':a' => $address, ':c' => $cityId, ':d' => $districtId, ':id' => $event['venue_id']]
                );
            } elseif ($venueName || $address) {
                $set[] = 'venue_id = :venue_id';
                $params[':venue_id'] = $this->db->insert(
                    "INSERT INTO venues (name, slug, address, city_id, district_id, state_id, country_id) VALUES (:n, :slug, :a, :c, :d, 1, 1)",
                    [':n' => mb_substr((string) ($venueName ?: $address), 0, 200), ':slug' => 'venue-' . bin2hex(random_bytes(6)), ':a' => $address, ':c' => $cityId, ':d' => $districtId]
                );
            }
            if (isset($apply['district']) && !empty($ctx['city_id'])) {
                $set[] = 'city_id = :city_id';
                $params[':city_id'] = $ctx['city_id'];
                $changes[] = 'location';
            }
            if (isset($apply['venue'])) $changes[] = 'venue';
            elseif (isset($apply['address'])) $changes[] = 'address';
        }

        $simple = [
            'registration_url' => ['registration_url', 'registration link'],
            'price'            => ['min_price', 'price'],
            'pricing_type'     => ['pricing_type', 'pricing'],
            'description'      => ['description', 'description'],
            'organizer'        => ['organizer_display_name', 'organizer'],
            'image'            => ['featured_image_url', 'image'],
        ];
        foreach ($simple as $field => [$col, $label]) {
            if (array_key_exists($field, $apply)) {
                $set[] = "{$col} = :{$col}";
                $params[":{$col}"] = $field === 'description' ? mb_substr((string) $apply[$field], 0, 20000) : $apply[$field];
                $changes[] = $label;
            }
        }
        if (isset($apply['description']) && !$event['short_summary']) {
            $set[] = 'short_summary = :summary';
            $params[':summary'] = mb_substr(preg_replace('/\s+/', ' ', (string) $apply['description']), 0, 300);
        }

        if ($set) {
            $this->db->update('UPDATE events SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE id = :id', $params);
        }
        return $changes;
    }

    private function touchVerified(int $eventId, array $ctx, ?int $newScore): void
    {
        if ($newScore !== null) {
            $this->db->update(
                "UPDATE events SET last_verified_at = NOW(), last_seen_at = NOW(), content_score = :s, content_source_id = :src WHERE id = :id",
                [':s' => $newScore, ':src' => $ctx['source_id'], ':id' => $eventId]
            );
        } else {
            $this->db->update("UPDATE events SET last_seen_at = NOW() WHERE id = :id", [':id' => $eventId]);
        }
    }

    /** @return bool true when a NEW open conflict was recorded */
    private function logConflict(array $event, string $field, mixed $cur, mixed $new, int $sourceId): bool
    {
        $newStr = mb_substr((string) $new, 0, 2000);
        $exists = $this->db->selectOne(
            "SELECT id FROM event_conflicts WHERE event_id = :e AND field = :f AND value_b = :v AND status = 'open' LIMIT 1",
            [':e' => $event['id'], ':f' => $field, ':v' => $newStr]
        );
        if ($exists) {
            return false;
        }
        $this->db->insert(
            "INSERT INTO event_conflicts (event_id, field, value_a, value_b, source_a_id, source_b_id, status)
             VALUES (:e, :f, :a, :b, :sa, :sb, 'open')",
            [':e' => $event['id'], ':f' => $field, ':a' => mb_substr((string) $cur, 0, 2000), ':b' => $newStr,
             ':sa' => $event['content_source_id'] ?? null, ':sb' => $sourceId]
        );
        return true;
    }

    private function same(string $field, mixed $cur, mixed $new): bool
    {
        if ($cur === null || $cur === '') return false;
        return match ($field) {
            'start', 'end'     => substr((string) $cur, 0, 16) === substr((string) $new, 0, 16),
            'registration_url' => $this->normUrl((string) $cur) === $this->normUrl((string) $new),
            'price'            => abs((float) $cur - (float) $new) < 0.01,
            'district'         => (int) $cur === (int) $new,
            default            => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $cur))) === mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $new))),
        };
    }

    private function normUrl(string $u): string
    {
        $p = parse_url(strtolower(trim($u)));
        return preg_replace('/^www\./', '', $p['host'] ?? '') . rtrim($p['path'] ?? '', '/') . (isset($p['query']) ? '?' . $p['query'] : '');
    }

    private function clean(mixed $v): ?string
    {
        if (!is_string($v)) return null;
        $v = trim(strip_tags($v));
        return $v === '' ? null : $v;
    }
}
