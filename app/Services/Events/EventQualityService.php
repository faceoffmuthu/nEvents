<?php

declare(strict_types=1);

namespace NEvents\Services\Events;

/**
 * Gate between "a source said this might be an event" and "this is fit to
 * show a real user". A search/crawl hit is never itself a published event —
 * everything discovered goes through here first (see IngestionService).
 */
class EventQualityService
{
    /**
     * @return string[] Empty array = passes. Non-empty = rejection reasons.
     */
    public function validate(array $record): array
    {
        $errors = [];

        $title = trim((string) ($record['title'] ?? ''));
        if ($title === '' || mb_strlen($title) < 4) {
            $errors[] = 'missing_or_too_short_title';
        } elseif ($this->looksLikeNonEventContent($title, (string) ($record['description'] ?? ''))) {
            $errors[] = 'not_an_event';
        }

        $start = $record['start_datetime'] ?? null;
        if (!$start) {
            $errors[] = 'missing_start_date';
        } else {
            try {
                $startDt = new \DateTimeImmutable($start, new \DateTimeZone('Asia/Kolkata'));
                $end     = $record['end_datetime'] ?? null;
                $cutoff  = $end ? new \DateTimeImmutable($end, new \DateTimeZone('Asia/Kolkata')) : $startDt;
                $now     = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata'));
                if ($cutoff < $now) {
                    $errors[] = 'event_already_past';
                }
            } catch (\Throwable) {
                $errors[] = 'unparseable_start_date';
            }
        }

        if (empty($record['source_url']) || !filter_var($record['source_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'missing_or_invalid_source_url';
        }

        if (!empty($record['registration_url']) && !filter_var($record['registration_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'invalid_registration_url';
        }

        $desc = trim((string) ($record['description'] ?? ''));
        if ($desc === '' && empty($record['venue_name']) && empty($record['city_raw'])) {
            $errors[] = 'insufficient_source_information';
        }

        if (!empty($record['status_raw']) && in_array(strtolower((string) $record['status_raw']), ['cancelled', 'canceled', 'postponed'], true)) {
            $errors[] = 'source_marked_cancelled_or_postponed';
        }

        return $errors;
    }

    /**
     * Cheap heuristics to reject pages that mention "event" but aren't one
     * (news articles, past recaps, job listings, product pages). Not a
     * substitute for structured data — this only runs on unstructured/loosely
     * extracted records as a last line of defense.
     */
    private function looksLikeNonEventContent(string $title, string $description): bool
    {
        $text = mb_strtolower($title . ' ' . $description);

        $rejectPatterns = [
            '/\bjob (opening|vacancy|listing)\b/',
            '/\bhiring\b/',
            '/\bpress release\b/',
            '/\brecap:?\b/',
            '/\bhow it went\b/',
            '/\breview of\b/',
            '/\bbuy now\b/',
            '/\bin stock\b/',
            '/\bterms (and|&) conditions\b/',
            '/\bprivacy policy\b/',
        ];
        foreach ($rejectPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Simple, explainable duplicate signal: same normalized title within the
     * same district and the same calendar day. Good enough as a first pass —
     * true fuzzy matching (venue/organizer/description similarity) can be
     * layered on without changing this method's contract.
     */
    public function duplicateSignature(string $title, ?int $districtId, ?string $startDatetime): string
    {
        $normalizedTitle = preg_replace('/[^a-z0-9]+/', '', mb_strtolower(trim($title)));
        $day = $startDatetime ? substr($startDatetime, 0, 10) : 'unknown';
        return $normalizedTitle . '|' . ($districtId ?? 'na') . '|' . $day;
    }
}
