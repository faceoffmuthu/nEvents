<?php

declare(strict_types=1);

namespace NEvents\Services\Events;

class CalendarService
{
    public function generateIcs(array $event, array $occurrences): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//N Events//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($occurrences as $occ) {
            // Stored values are UTC; the PHP default timezone is Asia/Kolkata,
            // so the ' UTC' suffix is required or times shift by 5h30.
            $startTs  = strtotime($occ['start_at_utc'] . ' UTC');
            $startUtc = gmdate('Ymd\THis\Z', $startTs);
            $endUtc   = $occ['end_at_utc']
                ? gmdate('Ymd\THis\Z', strtotime($occ['end_at_utc'] . ' UTC'))
                : gmdate('Ymd\THis\Z', $startTs + 7200);

            $uid      = 'event-' . $event['id'] . '-' . $occ['id'] . '@nevents.in';
            $summary  = $this->icsEscape($event['title']);
            $desc     = $this->icsEscape(($event['short_summary'] ?? '') . "\n\nRegister: " . ($event['registration_url'] ?? ''));
            $location = $this->icsEscape(($event['format'] ?? '') === 'online'
                ? 'Online'
                : implode(', ', array_filter([$event['venue_name'] ?? null, $event['venue_address'] ?? null, $event['district_name'] ?? ($event['city_name'] ?? null)])));
            $url      = $event['registration_url'] ?? '';

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $uid;
            $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
            $lines[] = 'DTSTART:' . $startUtc;
            $lines[] = 'DTEND:' . $endUtc;
            $lines[] = 'SUMMARY:' . $summary;
            $lines[] = 'DESCRIPTION:' . $desc;
            if ($location) $lines[] = 'LOCATION:' . $location;
            if ($url)      $lines[] = 'URL:' . $url;
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", $lines) . "\r\n";
    }

    private function icsEscape(string $value): string
    {
        $value = str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $value);
        return wordwrap($value, 75, "\r\n ", true);
    }
}
