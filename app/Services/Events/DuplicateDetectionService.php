<?php

declare(strict_types=1);

namespace NEvents\Services\Events;

use NEvents\Core\Database\Connection;

/**
 * Cross-source duplicate detection. Every path that creates a canonical
 * event — user submissions, organizer/admin creation, web/social ingestion —
 * asks this service first, so one real-world event gets one card.
 *
 * Candidates are events with an occurrence on the same local (IST) calendar
 * day. Each candidate is scored 0-100 from explainable signals:
 *
 *   title similarity     up to 50   (token overlap + character similarity)
 *   same district        15
 *   same start time      20        (within 15 minutes)
 *   venue similarity     up to 10
 *   same registration URL 25        (normalized, strong signal)
 *   same external ID     100        (same source post/page seen again)
 *   same organizer       10
 *
 * The caller decides what to do with the score using the configured
 * thresholds (discovery.duplicate_merge_score / duplicate_review_score).
 */
class DuplicateDetectionService
{
    private const STOPWORDS = ['the', 'a', 'an', 'and', 'of', 'in', 'at', 'for', 'to', 'on', 'with', '2024', '2025', '2026', '2027', 'event', 'tamil', 'nadu'];

    public function __construct(private Connection $db) {}

    /**
     * @param array{title:string, start_utc:?string, district_id:?int, venue_name?:?string,
     *              registration_url?:?string, organizer_name?:?string, external_id?:?string, source_id?:?int} $probe
     * @return array{event_id:int, score:int, signals:array}|null Best match, or null.
     */
    public function findBestMatch(array $probe, ?int $excludeEventId = null): ?array
    {
        if (!empty($probe['external_id']) && !empty($probe['source_id'])) {
            $row = $this->db->selectOne(
                "SELECT event_id FROM event_sources WHERE source_id = :s AND external_id = :x LIMIT 1",
                [':s' => $probe['source_id'], ':x' => $probe['external_id']]
            );
            if ($row && (int) $row['event_id'] !== $excludeEventId) {
                return ['event_id' => (int) $row['event_id'], 'score' => 100, 'signals' => ['external_id' => true]];
            }
        }

        if (empty($probe['start_utc'])) {
            return null;
        }

        // Same local calendar day (IST = UTC+05:30, fixed — see EventRepository notes)
        $day = (new \DateTimeImmutable($probe['start_utc'], new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('Asia/Kolkata'))->format('Y-m-d');

        $params = [':day' => $day];
        $sql = "SELECT e.id, e.title, e.registration_url, e.organizer_display_name,
                       o.name AS organizer_name, v.name AS venue_name, c.district_id,
                       MIN(eo.start_at_utc) AS start_at_utc
                  FROM events e
                  JOIN event_occurrences eo ON eo.event_id = e.id
                  LEFT JOIN cities c     ON c.id = e.city_id
                  LEFT JOIN venues v     ON v.id = e.venue_id
                  LEFT JOIN organizers o ON o.id = e.primary_organizer_id
                 WHERE DATE(DATE_ADD(eo.start_at_utc, INTERVAL 330 MINUTE)) = :day
                   AND e.status IN ('published','pending','postponed','draft')";
        if ($excludeEventId !== null) {
            $sql .= ' AND e.id <> :exclude';
            $params[':exclude'] = $excludeEventId;
        }
        $sql .= ' GROUP BY e.id LIMIT 200';

        $best = null;
        foreach ($this->db->select($sql, $params) as $cand) {
            [$score, $signals] = $this->score($probe, $cand);
            if ($best === null || $score > $best['score']) {
                $best = ['event_id' => (int) $cand['id'], 'score' => $score, 'signals' => $signals];
            }
        }

        return ($best && $best['score'] > 0) ? $best : null;
    }

    /** Records a pair for the admin duplicate-review queue (idempotent). */
    public function recordCandidate(int $eventA, int $eventB, int $score, array $signals): void
    {
        [$a, $b] = $eventA < $eventB ? [$eventA, $eventB] : [$eventB, $eventA];
        $this->db->statement(
            "INSERT INTO duplicate_candidates (event_a_id, event_b_id, score, signals_json, status)
             VALUES (:a, :b, :score, :signals, 'pending')
             ON DUPLICATE KEY UPDATE score = VALUES(score), signals_json = VALUES(signals_json)",
            [':a' => $a, ':b' => $b, ':score' => $score, ':signals' => json_encode($signals)]
        );
    }

    private function score(array $probe, array $cand): array
    {
        $signals = [];

        $titleSim = $this->titleSimilarity((string) $probe['title'], (string) $cand['title']);
        $signals['title_similarity'] = round($titleSim, 2);
        $score = (int) round($titleSim * 50);

        if (!empty($probe['district_id']) && (int) $probe['district_id'] === (int) ($cand['district_id'] ?? 0)) {
            $score += 15;
            $signals['same_district'] = true;
        }

        if (!empty($probe['start_utc']) && !empty($cand['start_at_utc'])
            && abs(strtotime($probe['start_utc'] . ' UTC') - strtotime($cand['start_at_utc'] . ' UTC')) <= 900) {
            $score += 20;
            $signals['same_start_time'] = true;
        }

        if (!empty($probe['venue_name']) && !empty($cand['venue_name'])) {
            $venueSim = $this->titleSimilarity($probe['venue_name'], $cand['venue_name']);
            if ($venueSim >= 0.6) {
                $score += (int) round($venueSim * 10);
                $signals['venue_similarity'] = round($venueSim, 2);
            }
        }

        if (!empty($probe['registration_url']) && !empty($cand['registration_url'])
            && $this->normalizeUrl($probe['registration_url']) === $this->normalizeUrl($cand['registration_url'])) {
            $score += 25;
            $signals['same_registration_url'] = true;
        }

        $candOrg = $cand['organizer_name'] ?: $cand['organizer_display_name'];
        if (!empty($probe['organizer_name']) && !empty($candOrg)
            && $this->normalizeText($probe['organizer_name']) === $this->normalizeText($candOrg)) {
            $score += 10;
            $signals['same_organizer'] = true;
        }

        // A weak title match alone is never a duplicate
        if ($titleSim < 0.5 && empty($signals['same_registration_url'])) {
            $score = min($score, 40);
        }

        return [min(100, $score), $signals];
    }

    public function titleSimilarity(string $a, string $b): float
    {
        $na = $this->normalizeText($a);
        $nb = $this->normalizeText($b);
        if ($na === '' || $nb === '') {
            return 0.0;
        }
        if ($na === $nb) {
            return 1.0;
        }

        $ta = array_values(array_diff(array_unique(explode(' ', $na)), self::STOPWORDS));
        $tb = array_values(array_diff(array_unique(explode(' ', $nb)), self::STOPWORDS));
        $jaccard = 0.0;
        if ($ta && $tb) {
            $inter   = count(array_intersect($ta, $tb));
            $union   = count(array_unique(array_merge($ta, $tb)));
            $jaccard = $union ? $inter / $union : 0.0;
        }

        similar_text($na, $nb, $pct);
        $sim = max($jaccard, $pct / 100 * 0.9);

        // One title fully contains the other ("Chennai AI Builders Meetup" vs
        // "Chennai AI Builders Meetup — October Edition"): strong signal
        $short = mb_strlen($na) <= mb_strlen($nb) ? $na : $nb;
        $long  = $short === $na ? $nb : $na;
        if (count(explode(' ', $short)) >= 3 && str_contains($long, $short)) {
            $sim = max($sim, 0.9);
        }
        return $sim;
    }

    public function normalizeText(string $s): string
    {
        $s = mb_strtolower($s);
        $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', (string) $s));
    }

    private function normalizeUrl(string $url): string
    {
        $p = parse_url(strtolower(trim($url)));
        $host = preg_replace('/^www\./', '', $p['host'] ?? '');
        return $host . rtrim($p['path'] ?? '', '/');
    }
}
