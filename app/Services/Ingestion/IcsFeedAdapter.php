<?php

declare(strict_types=1);

namespace NEvents\Services\Ingestion;

/**
 * Public iCalendar (.ics) feeds — the export many organizers, colleges,
 * community groups and public Google/Outlook calendars already publish.
 * Keyless, and the most precise source there is: explicit start/end times
 * with timezone, a stable UID and a STATUS field for cancellations.
 *
 * config_json: url or urls[]  (webcal:// is accepted and fetched over https)
 *
 * Recurring series (RRULE) are not expanded: the first instance is taken
 * and sent to review, so a weekly series never floods the list.
 */
class IcsFeedAdapter extends AbstractSourceAdapter
{
    public function isConfigured(array $config): bool
    {
        return !empty($config['url']) || !empty($config['urls']);
    }

    public function discover(array $config): array
    {
        $urls = array_merge(!empty($config['url']) ? [(string) $config['url']] : [], array_map('strval', (array) ($config['urls'] ?? [])));
        return array_values(array_unique(array_map(fn($u) => preg_replace('#^webcal://#i', 'https://', $u), array_filter($urls))));
    }

    public function fetch(string $url, array $config): string
    {
        return $this->httpGet($url);
    }

    public function extract(string $raw, string $url, array $config): array
    {
        if (stripos($raw, 'BEGIN:VCALENDAR') === false) {
            throw new \RuntimeException('Not an iCalendar feed: ' . $this->redact($url));
        }
        // RFC 5545 line unfolding: CRLF followed by a space/tab continues the line
        $text  = preg_replace("/\r\n[ \t]|\n[ \t]/", '', str_replace("\r\n", "\n", $raw));
        $calTz = preg_match('/^X-WR-TIMEZONE:(.+)$/mi', $text, $tm) ? trim($tm[1]) : null;

        $events = [];
        if (preg_match_all('/BEGIN:VEVENT\n(.*?)\nEND:VEVENT/s', $text, $blocks)) {
            foreach ($blocks[1] as $block) {
                $props = [];
                foreach (explode("\n", $block) as $line) {
                    if (!preg_match('/^([A-Z0-9-]+)((?:;[^:]*)?):(.*)$/i', trim($line), $pm)) continue;
                    $name = strtoupper($pm[1]);
                    $params = [];
                    foreach (array_filter(explode(';', ltrim($pm[2], ';'))) as $p) {
                        [$k, $v] = array_pad(explode('=', $p, 2), 2, '');
                        $params[strtoupper($k)] = trim($v, '"');
                    }
                    $props[$name] ??= ['value' => $pm[3], 'params' => $params];
                }
                $events[] = $props;
            }
        }
        return ['events' => $events, 'feed_url' => $url, 'cal_tz' => $calTz];
    }

    public function normalize(array $extracted, int $sourceId): array
    {
        $records = [];
        foreach ($extracted['events'] ?? [] as $p) {
            $title = $this->text($p['SUMMARY']['value'] ?? '');
            if ($title === '') continue;

            [$start, $startHasTime] = $this->date($p['DTSTART'] ?? null, $extracted['cal_tz'] ?? null);
            [$end]                  = $this->date($p['DTEND'] ?? null, $extracted['cal_tz'] ?? null);
            $location = $this->text($p['LOCATION']['value'] ?? '');
            $url      = trim($p['URL']['value'] ?? '');
            $url      = filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
            $status   = strtoupper(trim($p['STATUS']['value'] ?? ''));

            $reasons = [];
            $confidence = 92;                              // explicit machine-readable calendar data
            if (!$startHasTime) { $confidence = 65; $reasons[] = 'date_without_time'; }
            if (isset($p['RRULE'])) { $confidence = min($confidence, 70); $reasons[] = 'recurring_event_series'; }

            $organizer = $p['ORGANIZER']['params']['CN'] ?? null;
            $uid = trim($p['UID']['value'] ?? '') ?: sha1($title . '|' . $start);

            $records[] = [
                'source_id'        => $sourceId,
                'external_id'      => 'ics:' . substr($uid . (isset($p['RECURRENCE-ID']) ? '|' . $p['RECURRENCE-ID']['value'] : ''), 0, 480),
                'source_url'       => $url ?? $extracted['feed_url'],
                'confidence'       => $confidence,
                'review_reasons'   => $reasons,
                'status_raw'       => $status === 'CANCELLED' ? 'cancelled' : null,
                'title'            => mb_substr($title, 0, 300),
                'description'      => ($d = $this->text($p['DESCRIPTION']['value'] ?? '')) !== '' ? mb_substr(strip_tags($d), 0, 5000) : null,
                'start_datetime'   => $start,
                'end_datetime'     => $end,
                'start_tzid'       => ($p['DTSTART']['params']['TZID'] ?? null) ?: $calTz,
                'venue_name'       => $location !== '' ? trim(explode(',', $location)[0]) : null,
                'venue_address'    => $location !== '' ? $location : null,
                'format_raw'       => preg_match('#^(https?://|online|virtual|zoom|google meet)#i', $location) ? 'online' : null,
                'organizer_name'   => $organizer ? trim($organizer) : null,
                'registration_url' => $url,
                'fetched_at'       => date('Y-m-d H:i:s'),
            ];
        }
        return $records;
    }

    public function getSourceMetadata(array $config): array
    {
        return [
            'platform'           => 'web',
            'acquisition_method' => 'ics',
            'credentials'        => [],
            'configured'         => $this->isConfigured($config),
            'limitations'        => 'Public calendar feeds only. Recurring series are not expanded (first instance, held for review). Obeys robots.txt.',
        ];
    }

    /** @return array{0:?string, 1:bool} local Asia/Kolkata "Y-m-d H:i:s" and whether a time was given */
    private function date(?array $prop, ?string $calTz): array
    {
        if (!$prop) return [null, false];
        $v = trim($prop['value']);
        $local = new \DateTimeZone('Asia/Kolkata');
        try {
            if (preg_match('/^(\d{8})$/', $v) || ($prop['params']['VALUE'] ?? '') === 'DATE') {
                $d = \DateTimeImmutable::createFromFormat('!Ymd', substr($v, 0, 8), $local);
                return [$d ? $d->format('Y-m-d 00:00:00') : null, false];
            }
            if (!preg_match('/^(\d{8}T\d{4,6})(Z?)$/', $v, $m)) return [null, false];
            $tzName = $m[2] === 'Z' ? 'UTC' : ($prop['params']['TZID'] ?? $calTz ?? 'Asia/Kolkata');
            try { $tz = new \DateTimeZone($tzName); } catch (\Throwable) { $tz = $local; }
            $fmt = strlen($m[1]) === 15 ? '!Ymd\THis' : '!Ymd\THi';
            $d = \DateTimeImmutable::createFromFormat($fmt, $m[1], $tz);
            return [$d ? $d->setTimezone($local)->format('Y-m-d H:i:s') : null, (bool) $d];
        } catch (\Throwable) {
            return [null, false];
        }
    }

    private function text(string $v): string
    {
        return trim(str_replace(['\\n', '\\N', '\\,', '\\;', '\\\\'], ["\n", "\n", ',', ';', '\\'], $v));
    }
}
