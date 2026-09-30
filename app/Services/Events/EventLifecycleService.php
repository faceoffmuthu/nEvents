<?php

declare(strict_types=1);

namespace NEvents\Services\Events;

use NEvents\Core\Application;
use NEvents\Core\Database\Connection;

/**
 * Background freshness rules for canonical events (run by bin/scheduler.php):
 *
 *  - expirePastEvents(): events whose last occurrence has ended become
 *    status=completed (and drop out of upcoming lists, which already filter
 *    on start time).
 *  - refreshSourceFreshness(): after each successful run of a source, any
 *    event_sources row that source did NOT see again gets missing_count+1.
 *    Only after N consecutive misses is the reference marked missing and the
 *    event's verification_status set to 'outdated' — one failed fetch never
 *    deletes or hides anything.
 *  - purgeSocialPayloads(): drops stored social post text from processed
 *    candidates after the retention window (traceability fields are kept).
 */
class EventLifecycleService
{
    public function __construct(private Connection $db) {}

    public function expirePastEvents(): int
    {
        // An occurrence without an end time is treated as ending 3h after it starts
        $this->db->update(
            "UPDATE event_occurrences SET status = 'completed'
              WHERE status = 'scheduled'
                AND COALESCE(end_at_utc, DATE_ADD(start_at_utc, INTERVAL 3 HOUR)) < UTC_TIMESTAMP()"
        );
        return $this->db->update(
            "UPDATE events e SET e.status = 'completed', e.updated_at = NOW()
              WHERE e.status IN ('published','postponed')
                AND EXISTS (SELECT 1 FROM event_occurrences eo WHERE eo.event_id = e.id)
                AND NOT EXISTS (
                    SELECT 1 FROM event_occurrences eo
                     WHERE eo.event_id = e.id
                       AND eo.status IN ('scheduled','postponed')
                       AND COALESCE(eo.end_at_utc, DATE_ADD(eo.start_at_utc, INTERVAL 3 HOUR)) >= UTC_TIMESTAMP()
                )"
        );
    }

    /** @return array{checked:int, stale:int, missing:int} */
    public function refreshSourceFreshness(): array
    {
        $threshold = (int) Application::getInstance()->config('app.discovery.missing_runs_before_stale', 3);

        // Count one miss per successful source run that didn't re-see the reference
        $checked = $this->db->update(
            "UPDATE event_sources es
               JOIN sources s ON s.id = es.source_id
                SET es.missing_count = LEAST(es.missing_count + 1, 250),
                    es.last_checked_at = NOW(),
                    es.source_status = IF(es.missing_count >= :t1, 'missing', 'stale')   /* already incremented: SET runs left to right */
              WHERE s.last_success_at IS NOT NULL
                AND es.last_seen_at < s.last_success_at
                AND (es.last_checked_at IS NULL OR es.last_checked_at < s.last_success_at)
                AND es.source_status <> 'removed'",
            [':t1' => $threshold]
        );

        // Every reference of a still-upcoming published event has gone missing -> flag as outdated (not deleted)
        $missing = $this->db->update(
            "UPDATE events e SET e.verification_status = 'outdated'
              WHERE e.status = 'published' AND e.verification_status <> 'outdated'
                AND EXISTS (SELECT 1 FROM event_sources es WHERE es.event_id = e.id)
                AND NOT EXISTS (SELECT 1 FROM event_sources es WHERE es.event_id = e.id AND es.source_status IN ('active','stale'))"
        );

        $stale = (int) ($this->db->selectOne("SELECT COUNT(*) AS n FROM event_sources WHERE source_status = 'stale'")['n'] ?? 0);
        return ['checked' => $checked, 'stale' => $stale, 'missing' => $missing];
    }

    public function purgeSocialPayloads(): int
    {
        $days = (int) Application::getInstance()->config('app.discovery.social_raw_retention_days', 30);
        return $this->db->update(
            "UPDATE raw_event_records r JOIN sources s ON s.id = r.source_id
                SET r.raw_payload = NULL
              WHERE s.platform IN ('instagram','facebook','x')
                AND r.raw_payload IS NOT NULL
                AND r.processing_status IN ('normalized','duplicate','rejected','error')
                AND r.created_at < DATE_SUB(NOW(), INTERVAL :days DAY)",
            [':days' => $days]
        );
    }
}
