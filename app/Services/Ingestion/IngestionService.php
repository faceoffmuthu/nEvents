<?php

declare(strict_types=1);

namespace NEvents\Services\Ingestion;

use NEvents\Core\Application;
use NEvents\Core\Database\Connection;
use NEvents\Core\Log;
use NEvents\Services\Events\DuplicateDetectionService;
use NEvents\Services\Events\EventQualityService;
use NEvents\Services\Location\DistrictService;
use NEvents\Services\Notifications\NotificationService;
use Ramsey\Uuid\Uuid;

/**
 * Multi-source discovery pipeline (web pages, sitemaps, feeds, approved
 * social APIs, licensed search):
 *
 *   1. runSource()             SOURCE -> discover -> fetch -> extract ->
 *                              normalize -> raw_event_records CANDIDATE
 *                              (processing_status='pending'). Nothing here
 *                              is shown to users.
 *
 *   2. processPendingRecords() candidate -> social/extractor rejection ->
 *                              EventQualityService validation -> district
 *                              resolution -> cross-source duplicate check ->
 *                              trust x confidence decision:
 *                                - auto-publish (trusted source AND high
 *                                  extraction confidence AND no review flags)
 *                                - or canonical event held as pending /
 *                                  needs_review for a moderator
 *                              Duplicates attach an extra event_sources row
 *                              to the existing canonical event (one card).
 *
 * A search/crawl/social hit is never itself a published event.
 */
class IngestionService
{
    private const SOCIAL = ['instagram', 'facebook', 'x'];

    public function __construct(
        private Connection                $db,
        private EventQualityService       $quality,
        private DistrictService           $districts,
        private DuplicateDetectionService $duplicates,
        private NotificationService       $notifications,
        private \NEvents\Services\Events\EventMergeService $merger,
        private \NEvents\Services\Events\EventCategoryClassifier $categories,
        private Web\IndiaLocation         $india,
        private Web\OnlineAudience        $onlineAudience,
        private array                     $adapters = [], // class => SourceAdapterInterface
    ) {}

    public function registerAdapter(string $adapterClass, SourceAdapterInterface $adapter): void
    {
        $this->adapters[$adapterClass] = $adapter;
    }

    public function adapterFor(string $class): ?SourceAdapterInterface
    {
        return $this->adapters[$class] ?? null;
    }

    // ==================================================================
    // Stage 1 — candidates
    // ==================================================================

    public function runSource(int $sourceId): array
    {
        $source = $this->db->selectOne("SELECT * FROM sources WHERE id = :id AND enabled = 1", [':id' => $sourceId]);
        if (!$source) {
            throw new \RuntimeException("Source #{$sourceId} not found or disabled.");
        }

        $config  = json_decode($source['config_json'] ?? '{}', true) ?? [];
        $adapter = $this->resolveAdapter((string) $source['adapter_class']);

        if (!$adapter->isConfigured($config)) {
            $this->db->update("UPDATE sources SET health_status = 'disabled', last_checked_at = NOW() WHERE id = :id", [':id' => $sourceId]);
            $meta = $adapter->getSourceMetadata($config);
            throw new \RuntimeException("{$source['name']} is not configured — required: " . (implode(', ', $meta['credentials']) ?: 'config_json.url'));
        }

        $runId = $this->db->insert(
            "INSERT INTO source_runs (source_id, status, started_at) VALUES (:source_id, 'running', NOW())",
            [':source_id' => $sourceId]
        );

        $stats = ['discovered' => 0, 'fetched' => 0, 'inserted' => 0, 'seen_again' => 0, 'robots_skipped' => 0, 'errors' => 0];

        try {
            $items = $adapter->discover($config);
            $stats['discovered'] = count($items);

            foreach ($items as $item) {
                try {
                    $raw       = $adapter->fetch($item, $config);
                    $extracted = $adapter->extract($raw, $item, $config);
                    $records   = $adapter->normalize($extracted, $sourceId);
                    $stats['fetched']++;

                    foreach ($records as $record) {
                        if ($adapter->validate($record)) {
                            continue;
                        }
                        $this->storeCandidate($sourceId, $record, $item, $adapter, $stats);
                    }
                } catch (RobotsDisallowedException) {
                    $stats['robots_skipped']++;        // the site asked us not to — not an error
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    Log::get()->warning('source_fetch_failed', ['source_id' => $sourceId, 'error' => $e->getMessage()]);
                }
            }

            $partial = $stats['errors'] > 0 && $stats['fetched'] === 0 && $stats['discovered'] > 0;
            $this->db->update(
                "UPDATE source_runs SET status = :status, completed_at = NOW(), records_fetched = :fetched,
                        records_new = :new, records_updated = :updated, records_error = :errors,
                        memory_mb = :mem WHERE id = :id",
                [':status' => $partial ? 'failed' : ($stats['errors'] ? 'partial' : 'completed'),
                 ':fetched' => $stats['fetched'], ':new' => $stats['inserted'], ':updated' => $stats['seen_again'],
                 ':errors' => $stats['errors'], ':mem' => round(memory_get_peak_usage(true) / 1048576, 2), ':id' => $runId]
            );
            if ($partial) {
                $this->markSourceFailure($sourceId);
            } else {
                $this->db->update(
                    "UPDATE sources SET last_checked_at = NOW(), last_success_at = NOW(), error_count = 0,
                            health_status = :h WHERE id = :id",
                    [':h' => $stats['errors'] ? 'degraded' : 'healthy', ':id' => $sourceId]
                );
            }
        } catch (\Throwable $e) {
            $this->db->update(
                "UPDATE source_runs SET status = 'failed', completed_at = NOW(), error_message = :error,
                        records_fetched = :fetched, records_new = :new, records_error = :errors WHERE id = :id",
                [':error' => mb_substr($e->getMessage(), 0, 1000), ':fetched' => $stats['fetched'],
                 ':new' => $stats['inserted'], ':errors' => $stats['errors'], ':id' => $runId]
            );
            $this->markSourceFailure($sourceId);
            throw $e;
        }

        return $stats;
    }

    private function storeCandidate(int $sourceId, array $record, string $item, SourceAdapterInterface $adapter, array &$stats): void
    {
        $externalId = isset($record['external_id']) ? mb_substr((string) $record['external_id'], 0, 500) : null;
        // Every field EventMergeService can update is part of the fingerprint, so
        // a changed time, venue, price or link at the source is re-processed.
        $fingerprint = [];
        foreach (['source_url', 'title', 'start_datetime', 'end_datetime', 'status_raw', 'venue_name', 'venue_address', 'city_raw',
                  'registration_url', 'price_from', 'is_free', 'format_raw', 'organizer_name', 'description', 'image_url'] as $k) {
            $fingerprint[$k] = $record[$k] ?? null;
        }
        $hash = hash('sha256', json_encode($fingerprint, JSON_UNESCAPED_UNICODE));

        $existing = $externalId !== null
            ? $this->db->selectOne("SELECT id, content_hash, processing_status FROM raw_event_records WHERE source_id = :s AND external_id = :x", [':s' => $sourceId, ':x' => $externalId])
            : $this->db->selectOne("SELECT id, content_hash, processing_status FROM raw_event_records WHERE source_id = :s AND content_hash = :h", [':s' => $sourceId, ':h' => $hash]);

        if ($existing) {
            // Seen again: keeps linked canonical events fresh (last_seen_at)
            $this->db->update("UPDATE raw_event_records SET fetched_at = NOW() WHERE id = :id", [':id' => $existing['id']]);
            $this->db->update(
                "UPDATE event_sources SET last_seen_at = NOW(), last_checked_at = NOW(), missing_count = 0, source_status = 'active'
                  WHERE raw_event_record_id = :rid",
                [':rid' => $existing['id']]
            );
            // Content changed at the source (new date, now cancelled…) -> re-process
            if ($existing['content_hash'] !== $hash) {
                $this->db->update(
                    "UPDATE raw_event_records SET raw_payload = :p, content_hash = :h, confidence = :c,
                            processing_status = 'pending', error_message = NULL WHERE id = :id",
                    [':p' => json_encode($record, JSON_UNESCAPED_UNICODE), ':h' => $hash,
                     ':c' => isset($record['confidence']) ? (int) $record['confidence'] : null, ':id' => $existing['id']]
                );
            }
            $stats['seen_again']++;
            return;
        }

        $this->db->insert(
            "INSERT INTO raw_event_records
                (source_id, external_id, source_url, raw_payload, content_hash, confidence, parser_version, fetched_at, processing_status)
             VALUES (:source_id, :ext, :source_url, :raw_payload, :hash, :conf, :parser, NOW(), 'pending')",
            [
                ':source_id'   => $sourceId,
                ':ext'         => $externalId,
                ':source_url'  => mb_substr((string) ($record['source_url'] ?? $item), 0, 2000),
                ':raw_payload' => json_encode($record, JSON_UNESCAPED_UNICODE),
                ':hash'        => $hash,
                ':conf'        => isset($record['confidence']) ? (int) $record['confidence'] : null,
                ':parser'      => substr((string) strrchr(get_class($adapter), '\\'), 1, 20),
            ]
        );
        $stats['inserted']++;
    }

    private function markSourceFailure(int $sourceId): void
    {
        $this->db->update(
            "UPDATE sources SET last_checked_at = NOW(), error_count = error_count + 1,
                    health_status = IF(error_count >= 3, 'failing', 'degraded') WHERE id = :id",   // error_count is already incremented here (SET is evaluated left to right)
            [':id' => $sourceId]
        );
    }

    // ==================================================================
    // Stage 2 — candidates -> canonical events
    // ==================================================================

    /**
     * @return array{processed:int, published:int, review:int, updated:int, duplicate:int, rejected:int, cancelled:int}
     */
    public function processPendingRecords(?int $sourceId = null, int $limit = 50): array
    {
        $where  = ["r.processing_status = 'pending'"];
        $params = [':limit' => $limit];
        if ($sourceId !== null) {
            $where[] = 'r.source_id = :source_id';
            $params[':source_id'] = $sourceId;
        }

        $rows = $this->db->select(
            "SELECT r.*, s.trust_level, s.platform, s.name AS source_name, s.config_json AS source_config
               FROM raw_event_records r JOIN sources s ON s.id = r.source_id
              WHERE " . implode(' AND ', $where) . " ORDER BY r.discovered_at ASC LIMIT :limit",
            $params
        );

        $result = ['processed' => 0, 'published' => 0, 'review' => 0, 'updated' => 0, 'duplicate' => 0, 'rejected' => 0, 'cancelled' => 0];
        $cfg = Application::getInstance()->config('app.discovery', []);

        foreach ($rows as $row) {
            $result['processed']++;
            try {
                $outcome = $this->processOne($row, $cfg);
            } catch (\Throwable $e) {
                $this->markRecord((int) $row['id'], 'error', mb_substr($e->getMessage(), 0, 500));
                Log::get()->error('candidate_processing_failed', ['raw_id' => $row['id'], 'error' => $e->getMessage()]);
                $outcome = 'rejected';
            }
            $result[$outcome]++;
        }
        return $result;
    }

    /** @return string one of published|review|updated|duplicate|rejected|cancelled */
    private function processOne(array $row, array $cfg): string
    {
        $record = json_decode($row['raw_payload'] ?? '{}', true) ?? [];

        // Social extractor already decided this is not an event (recap, job post, no date…)
        if (!empty($record['reject_reason'])) {
            $this->markRecord((int) $row['id'], 'rejected', 'social_filter: ' . $record['reject_reason']);
            return 'rejected';
        }

        $format = in_array($record['format_raw'] ?? null, ['online', 'hybrid'], true) ? $record['format_raw'] : 'offline';
        $districtId = null;
        if ($format !== 'online') {
            // A page that states another country is not an event in India, whatever its place names
            $country = strtolower(trim((string) ($record['country_raw'] ?? '')));
            if ($country !== '' && !in_array($country, ['in', 'ind', 'india', 'bharat'], true)) {
                $this->markRecord((int) $row['id'], 'rejected', 'outside_india');
                return 'rejected';
            }
            $districtId = $this->resolveDistrict($record);
        }

        $startUtc = !empty($record['start_datetime']) ? $this->toUtc($record['start_datetime']) : null;
        $probe = [
            'title'            => (string) ($record['title'] ?? ''),
            'start_utc'        => $startUtc,
            'district_id'      => $districtId,
            'venue_name'       => $record['venue_name'] ?? null,
            'registration_url' => $record['registration_url'] ?? null,
            'organizer_name'   => $record['organizer_name'] ?? null,
            'external_id'      => $record['external_id'] ?? null,
            'source_id'        => (int) $row['source_id'],
        ];

        // The source says the event is cancelled/postponed: update the canonical event if we have it
        $statusRaw = strtolower((string) ($record['status_raw'] ?? ''));
        if (in_array($statusRaw, ['cancelled', 'canceled', 'postponed', 'rescheduled'], true)) {
            $match = $this->duplicates->findBestMatch($probe);
            if ($match && $match['score'] >= (int) ($cfg['duplicate_merge_score'] ?? 85)) {
                $this->applySourceStatus($match['event_id'], $statusRaw === 'canceled' ? 'cancelled' : ($statusRaw === 'rescheduled' ? 'postponed' : $statusRaw), $row);
                $this->markRecord((int) $row['id'], 'duplicate', "source reports {$statusRaw} for event #{$match['event_id']}");
                return 'cancelled';
            }
            $this->markRecord((int) $row['id'], 'rejected', 'source_marked_cancelled_or_postponed');
            return 'rejected';
        }

        // Online events: only from India or Canada AND in English/Tamil/Hindi/Malayalam/Telugu
        // (unknown country = rejected). Events people post themselves are not filtered here.
        if ($format === 'online') {
            $aud = $this->onlineAudience->check($record);
            if (!$aud['allowed']) {
                $this->markRecord((int) $row['id'], 'rejected', $aud['reason']);
                return 'rejected';
            }
        }

        $errors = $this->quality->validate($record);
        if ($errors) {
            $this->markRecord((int) $row['id'], 'rejected', implode(', ', $errors));
            return 'rejected';
        }

        // Duplicate check BEFORE the district requirement: another source's
        // post about an event we already have only needs to be linked to it.
        $match = $this->duplicates->findBestMatch($probe);
        $mergeAt  = (int) ($cfg['duplicate_merge_score'] ?? 85);
        $reviewAt = (int) ($cfg['duplicate_review_score'] ?? 60);

        if ($match && $match['score'] >= $mergeAt) {
            // Same event (another source, or this source's updated page): keep
            // ONE record, link the source, and apply newer/more trusted details.
            $this->attachSource($match['event_id'], $row, $record, false);
            $this->categories->assignIfMissing($match['event_id'], $record);
            $changes = $this->merger->merge($match['event_id'], $record, [
                'source_id'   => (int) $row['source_id'],
                'trust'       => (int) $row['trust_level'],
                'confidence'  => (int) ($record['confidence'] ?? 60),
                'start_utc'   => $startUtc,
                'end_utc'     => !empty($record['end_datetime']) ? $this->toUtc($record['end_datetime']) : null,
                'district_id' => $districtId,
                'city_id'     => $districtId !== null ? $this->cityForDistrict($districtId, $record) : null,
                'format'      => $format,
            ]);
            $this->markRecord((int) $row['id'], 'duplicate', mb_substr("matched event #{$match['event_id']} (score {$match['score']})"
                . ($changes ? '; ' . implode('; ', $changes) : '; no changes'), 0, 1000));
            return $changes ? 'updated' : 'duplicate';
        }

        $locality = null;
        if ($format !== 'online' && $districtId === null) {
            // No district matched. National platforms (config_json.region_scope = "india"):
            // keep an event that is identifiably in India, labelled "City, State";
            // drop anything that isn't identifiably in India.
            $scope = (json_decode((string) ($row['source_config'] ?? ''), true) ?: [])['region_scope'] ?? 'tamil_nadu';
            $where = $scope === 'india' ? $this->india->classify($record) : null;
            if ($where !== null && $where['in_india'] !== true) {
                $this->markRecord((int) $row['id'], 'rejected', $where['in_india'] === false ? 'outside_india' : 'location_not_identified_as_india');
                return 'rejected';
            }
            if ($where === null || str_contains((string) $where['label'], 'Tamil Nadu')) {
                // "Do not randomly assign a district" — hold the candidate for a human
                $this->markRecord((int) $row['id'], 'needs_review', 'district_could_not_be_determined');
                return 'review';
            }
            $locality = $where['label'];
        }

        $reasons = (array) ($record['review_reasons'] ?? []);
        if ($match && $match['score'] >= $reviewAt) {
            $reasons[] = 'possible_duplicate_of_event_' . $match['event_id'];
        }
        $confidence = (int) ($record['confidence'] ?? 60);
        $trust      = (int) $row['trust_level'];
        $publish    = !$reasons
            && $confidence >= (int) ($cfg['auto_publish_min_confidence'] ?? 80)
            && $trust >= (int) ($cfg['auto_publish_min_trust'] ?? 70);

        if (!$publish && $trust < (int) ($cfg['auto_publish_min_trust'] ?? 70)) {
            $reasons[] = "source_trust_{$trust}";
        }
        if (!$publish && $confidence < (int) ($cfg['auto_publish_min_confidence'] ?? 80)) {
            $reasons[] = "extraction_confidence_{$confidence}";
        }

        $eventId = $this->createEvent($record, $districtId, $format, $startUtc, $publish, array_values(array_unique($reasons)), $trust, $confidence, (int) $row['source_id'], $locality);
        $this->attachSource($eventId, $row, $record, true);
        $this->categories->assignIfMissing($eventId, $record);
        if ($match && $match['score'] >= $reviewAt) {
            $this->duplicates->recordCandidate($match['event_id'], $eventId, $match['score'], $match['signals'] + ['origin' => 'discovery']);
        }
        $this->markRecord((int) $row['id'], $publish ? 'normalized' : 'needs_review', $publish ? null : implode(',', array_unique($reasons)));

        return $publish ? 'published' : 'review';
    }

    /**
     * Structured locality/region/address first, then venue name — each via
     * DistrictService's exact name/alias/area/city lookup (never substring guessing).
     */
    private function resolveDistrict(array $record): ?int
    {
        $context = $this->placeText($record);   // a state named anywhere settles names shared by several states
        foreach (['city_raw', 'region_raw', 'venue_address', 'venue_name'] as $field) {
            $text = trim((string) ($record[$field] ?? ''));
            if ($text !== '' && ($id = $this->districts->resolveDistrictId($text, $context)) !== null) {
                return $id;
            }
        }
        return null;
    }

    private function placeText(array $record): string
    {
        return implode(', ', array_filter(array_map(
            fn ($f) => is_string($record[$f] ?? null) ? trim($record[$f]) : '',
            ['city_raw', 'region_raw', 'venue_address', 'venue_name', 'country_raw']
        )));
    }

    private function applySourceStatus(int $eventId, string $status, array $row): void
    {
        $event = $this->db->selectOne("SELECT id, status, data_origin, created_by_user_id FROM events WHERE id = :id", [':id' => $eventId]);
        if (!$event || $event['status'] === $status) {
            return;
        }
        // A community member's own listing is theirs to manage: flag it instead of overriding it
        if ($event['created_by_user_id']) {
            $this->db->update(
                "UPDATE events SET moderation_status = 'flagged', moderation_reason = :r WHERE id = :id",
                [':r' => "external source reports {$status}: " . mb_substr((string) $row['source_url'], 0, 300), ':id' => $eventId]
            );
            return;
        }
        $this->db->update("UPDATE events SET status = :s, updated_at = NOW() WHERE id = :id", [':s' => $status, ':id' => $eventId]);
        $this->db->update(
            "UPDATE event_occurrences SET status = :s WHERE event_id = :id AND status = 'scheduled'",
            [':s' => $status === 'cancelled' ? 'cancelled' : 'postponed', ':id' => $eventId]
        );
        $this->notifications->cancelQueuedForEvent($eventId);
        if ($event['status'] === 'published') {
            $this->notifications->notifySaversOfChange($eventId, $status === 'cancelled' ? 'cancelled' : 'postponed', 'The organizer\'s page now lists it as ' . $status . '.');
        }
        Log::get()->info('event_status_from_source', ['event_id' => $eventId, 'status' => $status, 'source' => $row['source_id']]);
    }

    /** The city the record names within the district (Secunderabad, Navi Mumbai), else its main city. */
    private function cityForDistrict(int $districtId, array $record): ?int
    {
        return $this->districts->cityIdFor($districtId, $this->placeText($record));
    }

    private function createEvent(array $record, ?int $districtId, string $format, ?string $startUtc,
                                 bool $publish, array $reasons, int $trust, int $confidence, int $sourceId, ?string $locality = null): int
    {
        $cityId  = $districtId !== null ? $this->cityForDistrict($districtId, $record) : null;
        $stateId = $districtId !== null ? ((int) ($this->districts->findById($districtId)['state_id'] ?? 0) ?: null) : null;

        $venueId = null;
        if ($format !== 'online' && (!empty($record['venue_name']) || !empty($record['venue_address']))) {
            $venueId = $this->db->insert(
                "INSERT INTO venues (name, slug, address, locality, city_id, district_id, state_id, country_id)
                 VALUES (:n, :slug, :a, :loc, :c, :d, :st, 1)",
                [':n' => mb_substr((string) ($record['venue_name'] ?: $record['venue_address']), 0, 200),
                 ':slug' => 'venue-' . bin2hex(random_bytes(6)), ':a' => $record['venue_address'] ?? null,
                 ':loc' => $locality, ':c' => $cityId, ':d' => $districtId, ':st' => $stateId]
            );
        }

        $title = mb_substr(trim((string) $record['title']), 0, 300);
        $desc  = isset($record['description']) ? trim(strip_tags((string) $record['description'])) : null;

        $eventId = $this->db->insert(
            "INSERT INTO events
                (uuid, slug, title, normalized_title, short_summary, description, organizer_display_name, event_type, format,
                 status, data_origin, moderation_status, moderation_reason, verification_status, trust_score,
                 content_score, content_source_id,
                 registration_url, timezone, pricing_type, currency, min_price, venue_id, city_id, featured_image_url,
                 discovered_at, first_published_at, published_at, last_verified_at, last_seen_at)
             VALUES
                (:uuid, :slug, :title, :ntitle, :summary, :description, :org, 'discovered', :format,
                 :status, 'discovered', :moderation, :reason, 'unverified', :trust,
                 :cscore, :csrc,
                 :registration_url, 'Asia/Kolkata', :pricing_type, 'INR', :min_price, :venue_id, :city_id, :image_url,
                 NOW(), :pub1, :pub2, NOW(), NOW())",
            [
                ':uuid'             => Uuid::uuid4()->toString(),
                ':slug'             => $this->uniqueSlug($title),
                ':title'            => $title,
                ':ntitle'           => $this->normalizeTitle($title),
                ':summary'          => $desc !== null ? mb_substr(preg_replace('/\s+/', ' ', $desc), 0, 300) : null,
                ':description'      => $desc,
                ':org'              => isset($record['organizer_name']) ? mb_substr((string) $record['organizer_name'], 0, 200) : null,
                ':format'           => $format,
                ':status'           => $publish ? 'published' : 'pending',
                ':moderation'       => $publish ? 'clean' : 'needs_review',
                ':reason'           => $publish ? null : mb_substr(implode(',', $reasons), 0, 500),
                ':trust'            => (int) round(($trust + $confidence) / 2),
                ':cscore'           => $reasons ? min(59, (int) round(($trust + $confidence) / 2)) : (int) round(($trust + $confidence) / 2),
                ':csrc'             => $sourceId,
                ':registration_url' => $record['registration_url'] ?? null,
                ':pricing_type'     => !empty($record['is_free']) ? 'free' : (!empty($record['price_from']) ? 'paid' : 'unknown'),
                ':min_price'        => $record['price_from'] ?? null,
                ':venue_id'         => $venueId,
                ':city_id'          => $cityId,
                ':image_url'        => $record['image_url'] ?? null,
                ':pub1'             => $publish ? date('Y-m-d H:i:s') : null,
                ':pub2'             => $publish ? date('Y-m-d H:i:s') : null,
            ]
        );

        if ($startUtc) {
            $this->db->insert(
                "INSERT INTO event_occurrences (event_id, start_at_utc, end_at_utc, timezone, status)
                 VALUES (:eid, :start, :end, 'Asia/Kolkata', 'scheduled')",
                [':eid' => $eventId, ':start' => $startUtc, ':end' => !empty($record['end_datetime']) ? $this->toUtc($record['end_datetime']) : null]
            );
        }

        return (int) $eventId;
    }

    private function attachSource(int $eventId, array $rawRow, array $record, bool $isPrimary): void
    {
        $existing = !empty($record['external_id'])
            ? $this->db->selectOne("SELECT id FROM event_sources WHERE event_id = :e AND source_id = :s AND external_id = :x",
                [':e' => $eventId, ':s' => $rawRow['source_id'], ':x' => $record['external_id']])
            : false;
        if ($existing) {
            $this->db->update("UPDATE event_sources SET last_seen_at = NOW(), last_checked_at = NOW(), missing_count = 0, source_status = 'active' WHERE id = :id",
                [':id' => $existing['id']]);
            return;
        }

        $this->db->insert(
            "INSERT INTO event_sources
                (event_id, source_id, raw_event_record_id, external_id, account_handle, source_url, registration_url,
                 source_status, first_seen_at, last_seen_at, last_verified_at, last_checked_at, is_primary, confidence)
             VALUES (:eid, :sid, :rid, :ext, :handle, :source_url, :reg_url, 'active', NOW(), NOW(), NOW(), NOW(), :is_primary, :conf)",
            [
                ':eid'        => $eventId,
                ':sid'        => $rawRow['source_id'],
                ':rid'        => $rawRow['id'],
                ':ext'        => $record['external_id'] ?? null,
                ':handle'     => isset($record['account_handle']) ? mb_substr((string) $record['account_handle'], 0, 200) : null,
                ':source_url' => $record['source_url'] ?? $rawRow['source_url'],
                ':reg_url'    => $record['registration_url'] ?? null,
                ':is_primary' => $isPrimary ? 1 : 0,
                ':conf'       => (int) ($record['confidence'] ?? 50),
            ]
        );
    }

    private function markRecord(int $id, string $status, ?string $errorMessage): void
    {
        $this->db->update(
            "UPDATE raw_event_records SET processing_status = :status, error_message = :err WHERE id = :id",
            [':status' => $status, ':err' => $errorMessage, ':id' => $id]
        );
    }

    private function normalizeTitle(string $title): string
    {
        return mb_substr(preg_replace('/\s+/', ' ', mb_strtolower(trim($title))), 0, 300);
    }

    private function uniqueSlug(string $title): string
    {
        $base = mb_substr(trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($title)), '-') ?: 'event', 0, 80);
        $slug = $base;
        $i = 1;
        while ($this->db->selectOne('SELECT id FROM events WHERE slug = :s', [':s' => $slug])) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }

    /** Local (Asia/Kolkata) wall-clock time -> UTC. */
    private function toUtc(string $datetime): string
    {
        try {
            $dt = new \DateTimeImmutable($datetime, new \DateTimeZone('Asia/Kolkata'));
            return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return $datetime;
        }
    }

    private function resolveAdapter(string $class): SourceAdapterInterface
    {
        if (isset($this->adapters[$class])) {
            return $this->adapters[$class];
        }
        throw new \RuntimeException("No adapter registered for class: {$class}");
    }

    public static function isSocialPlatform(string $platform): bool
    {
        return in_array($platform, self::SOCIAL, true);
    }
}
