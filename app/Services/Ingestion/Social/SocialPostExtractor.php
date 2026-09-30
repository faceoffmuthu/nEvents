<?php

declare(strict_types=1);

namespace NEvents\Services\Ingestion\Social;

use NEvents\Services\Security\UrlValidator;

/**
 * Turns one social post (caption/message/tweet text + metadata) into an
 * event CANDIDATE record — or a rejection. Deliberately conservative:
 *
 *  - Recaps, thank-you posts, job posts, product ads, news and posts that
 *    say the event was cancelled are rejected outright.
 *  - A post needs several independent event signals (registration words,
 *    "venue:", "date:", RSVP, tickets…) — merely mentioning "event" is not enough.
 *  - Dates are NEVER invented. Explicit "25 Oct 2026, 6 PM" is high
 *    confidence; a missing year, missing time, relative wording ("this
 *    Saturday"), ambiguous numeric dates or several different dates lower the
 *    confidence so the candidate goes to review instead of being published.
 *  - Location comes from structured place data, an explicit "Venue:/📍/
 *    Location:" line, or the source account's configured home district —
 *    never from random words or hashtags in the caption.
 */
class SocialPostExtractor
{
    private const MONTHS = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'sept' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    private const REJECT = [
        'past_event_recap'   => '/\b(thank(s| you)\s+(to\s+)?(all|everyone|every one)\b.*\b(came|attend|join|participat|part)|thank(s| you) for (coming|attending|joining|being (a )?part|making)|was a (huge |great |grand )?success|highlights? (from|of)|glimpses? (from|of)|\brecap\b|throwback|#tbt|memories from|what an? (amazing |incredible |great )?(day|night|evening|event|session)!|we (had|hosted) (an? )?(amazing|great|wonderful|fantastic)|it was (great|amazing|wonderful|a pleasure))/iu',
        'job_post'           => '/\b(we\'?re hiring|we are hiring|job (opening|opportunit|vacanc)|vacanc(y|ies)|apply (now )?for (the|this) (role|position)|hiring for|walk-?in interview|send your (cv|resume))\b/iu',
        'product_ad'         => '/\b(buy now|shop now|order now|\d+\s?% off|flat \d+%|discount code|use code [A-Z0-9]+|limited stock|free delivery|add to cart)\b/iu',
        'news'               => '/\b(breaking news|according to (reports|sources)|press release|news update)\b/iu',
        'cancelled'          => '/\b(event (is |has been )?(cancel+ed|postponed|called off)|(cancel+ed|postponed) (due to|until))\b/iu',
    ];

    private const EVENT_SIGNALS = [
        '/\bregist(er|ration)\b/iu', '/\brsvp\b/iu', '/\btickets?\b/iu', '/\bentry\s*(fee|free|:)/iu', '/\bjoin us\b/iu',
        '/\bvenue\s*[:\-]/iu', '/\bdate\s*[:\-]/iu', '/\btime\s*[:\-]/iu', '/\bwhen\s*[:\-]/iu', '/\bwhere\s*[:\-]/iu',
        '/📅|🗓|📍|⏰|🕒/u', '/\bsave the date\b/iu', '/\blimited seats\b/iu', '/\bseats? (are )?(limited|filling)\b/iu',
        '/\b(meetup|workshop|conference|summit|hackathon|webinar|bootcamp|masterclass|seminar|expo|fest|marathon|walkathon|cyclothon|ride|networking (event|session|evening))\b/iu',
        '/\bspeakers?\b/iu', '/\bagenda\b/iu', '/\blink in bio\b/iu',
    ];

    private const ONLINE = '/\b(online|webinar|virtual|zoom|google meet|ms teams|microsoft teams|youtube live|livestream|live stream)\b/iu';

    private const REG_HOSTS = '/(forms\.gle|docs\.google\.com\/forms|konfhub|townscript|eventbrite|meetup\.com|allevents|lu\.ma|luma\.com|bookmyshow|insider\.in|commudle|devfolio|unstop|hasgeek|register|registration|rsvp|tickets?)/i';

    /**
     * @param array{text:string, posted_at:?string, permalink:?string, place_text?:?string,
     *              account_handle?:?string, account_name?:?string, account_district?:?string,
     *              urls?:array<string>, image_url?:?string} $post
     * @return array Candidate record (see SourceAdapterInterface::normalize) — contains
     *               'reject_reason' when the post must not become an event.
     */
    public function analyze(array $post): array
    {
        $text     = $this->clean((string) ($post['text'] ?? ''));
        $postedAt = $this->parsePosted($post['posted_at'] ?? null);
        $base = [
            'source_url'     => $post['permalink'] ?? null,
            'account_handle' => $post['account_handle'] ?? null,
            'organizer_name' => $post['account_name'] ?? ($post['account_handle'] ?? null),
            'description'    => mb_substr($text, 0, 2000),
            'posted_at'      => $postedAt->format('Y-m-d H:i:s'),
            'review_reasons' => [],
            'confidence'     => 0,
        ];

        if (mb_strlen($text) < 40) {
            return $base + ['reject_reason' => 'too_little_text', 'title' => null];
        }
        foreach (self::REJECT as $reason => $pattern) {
            if (preg_match($pattern, $text)) {
                return $base + ['reject_reason' => $reason, 'title' => $this->title($text)];
            }
        }

        $signals = 0;
        foreach (self::EVENT_SIGNALS as $p) {
            $signals += preg_match($p, $text) ? 1 : 0;
        }
        if ($signals < 2) {
            return $base + ['reject_reason' => 'not_an_event_announcement', 'title' => $this->title($text)];
        }

        $date = $this->extractDate($text, $postedAt);
        if ($date === null) {
            return $base + ['reject_reason' => 'no_event_date_found', 'title' => $this->title($text)];
        }
        if ($date['start'] < $postedAt->modify('-1 day')) {
            // Date is before the post itself -> talking about something that already happened
            return $base + ['reject_reason' => 'date_before_post', 'title' => $this->title($text)];
        }

        $confidence = $date['confidence'];
        $review     = $date['reasons'];

        $isOnline = (bool) preg_match(self::ONLINE, $text);
        $location = $this->extractLocation($text, $post['place_text'] ?? null);
        $cityRaw  = null;
        if ($location === null && !$isOnline && !empty($post['account_district'])) {
            $cityRaw = $post['account_district'];   // source metadata, not caption guessing
            $confidence -= 10;
            $review[] = 'location_from_account_home_district';
        } elseif ($location === null && !$isOnline) {
            $review[] = 'no_location_found';
            $confidence -= 30;
        }

        if ($signals < 3) {
            $confidence -= 10;
            $review[] = 'weak_event_signals';
        }

        $registration = $this->registrationUrl($text, $post['urls'] ?? []);
        $price = $this->price($text);

        return array_merge($base, [
            'title'            => $this->title($text),
            'start_datetime'   => $date['start']->format('Y-m-d H:i:s'),
            'end_datetime'     => $date['end']?->format('Y-m-d H:i:s'),
            'venue_name'       => $location['venue'] ?? null,
            'venue_address'    => $location['address'] ?? null,
            'city_raw'         => $cityRaw,
            'format_raw'       => $isOnline && $location === null ? 'online' : ($isOnline ? 'hybrid' : null),
            'registration_url' => $registration,
            'is_free'          => $price['free'] ? 1 : null,
            'price_from'       => $price['from'],
            'image_url'        => $post['image_url'] ?? null,
            'confidence'       => max(0, min(100, $confidence)),
            'review_reasons'   => array_values(array_unique($review)),
        ]);
    }

    // ------------------------------------------------------------------

    /**
     * @return array{start:\DateTimeImmutable, end:?\DateTimeImmutable, confidence:int, reasons:string[]}|null
     */
    public function extractDate(string $text, \DateTimeImmutable $postedAt): ?array
    {
        $tz  = new \DateTimeZone('Asia/Kolkata');
        $mon = '(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|june?|july?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)';
        $found   = [];   // Y-m-d => [flags]
        $reasons = [];

        // "25 Oct 2026", "25th October", "25-Oct-2026"
        if (preg_match_all('/\b(\d{1,2})(?:st|nd|rd|th)?[\s\-\/\.]*' . $mon . '\.?[,\s\-\/\.]*(\d{4})?\b/iu', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $x) $this->addDate($found, (int) $x[1], $this->month($x[2]), $x[3] ?? '', $postedAt, false);
        }
        // "October 25, 2026", "Oct 25th"
        if (preg_match_all('/\b' . $mon . '\.?\s+(\d{1,2})(?:st|nd|rd|th)?\b,?\s*(\d{4})?/iu', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $x) $this->addDate($found, (int) $x[2], $this->month($x[1]), $x[3] ?? '', $postedAt, false);
        }
        // "25/10/2026", "25-10-26" — Indian day-first order
        if (preg_match_all('/\b(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4}|\d{2})\b/', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $x) {
                $ambiguous = (int) $x[1] <= 12 && (int) $x[2] <= 12 && $x[1] !== $x[2];
                $year = strlen($x[3]) === 2 ? '20' . $x[3] : $x[3];
                $this->addDate($found, (int) $x[1], (int) $x[2], $year, $postedAt, $ambiguous);
            }
        }

        $relative = false;
        if (!$found) {
            // Relative wording only: resolved against the post time, low confidence
            if (preg_match('/\btomorrow\b/iu', $text)) {
                $found[$postedAt->modify('+1 day')->format('Y-m-d')] = ['year_inferred' => false, 'ambiguous' => false];
                $relative = true;
            } elseif (preg_match('/\b(this|coming|next)\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/iu', $text, $rm)) {
                $d = $postedAt->modify(($rm[1] === 'next' ? 'next ' : '') . $rm[2]);
                if ($rm[1] === 'next' && $d->diff($postedAt)->days < 7) $d = $d->modify('+7 days');
                $found[$d->format('Y-m-d')] = ['year_inferred' => false, 'ambiguous' => false];
                $relative = true;
            } elseif (preg_match('/\btoday\b|\btonight\b/iu', $text)) {
                $found[$postedAt->format('Y-m-d')] = ['year_inferred' => false, 'ambiguous' => false];
                $relative = true;
            }
        }
        if (!$found) {
            return null;
        }

        ksort($found);
        $days       = array_keys($found);
        $confidence = 100;

        if ($relative) {
            $confidence -= 45;
            $reasons[] = 'relative_date_wording';
        }
        foreach ($found as $flags) {
            if ($flags['year_inferred']) { $confidence -= 20; $reasons[] = 'year_not_stated'; break; }
        }
        foreach ($found as $flags) {
            if ($flags['ambiguous']) { $confidence -= 15; $reasons[] = 'ambiguous_numeric_date'; break; }
        }

        $endDay = null;
        if (count($days) === 2) {
            $gap = (new \DateTimeImmutable($days[0]))->diff(new \DateTimeImmutable($days[1]))->days;
            if ($gap <= 7 && preg_match('/\b(to|till|until|and)\b|[–—-]/u', $text)) {
                $endDay = $days[1];                    // "25–26 Oct" style multi-day event
            } else {
                $confidence -= 40;
                $reasons[] = 'multiple_different_dates';
            }
        } elseif (count($days) > 2) {
            $confidence -= 40;
            $reasons[] = 'multiple_different_dates';
        }

        [$startTime, $endTime] = $this->extractTimes($text);
        if ($startTime === null) {
            $confidence -= 25;
            $reasons[] = 'time_not_stated';
        }

        $start = new \DateTimeImmutable($days[0] . ' ' . ($startTime ?? '00:00'), $tz);
        $end   = null;
        if ($endDay !== null || $endTime !== null) {
            $end = new \DateTimeImmutable(($endDay ?? $days[0]) . ' ' . ($endTime ?? ($startTime ?? '23:59')), $tz);
            if ($end < $start) $end = null;
        }

        return ['start' => $start, 'end' => $end, 'confidence' => $confidence, 'reasons' => $reasons];
    }

    /** @return array{0:?string, 1:?string} "H:i" start/end */
    private function extractTimes(string $text): array
    {
        $times = [];
        if (preg_match_all('/\b(\d{1,2})(?:[:\.](\d{2}))?\s*(a\.?m\.?|p\.?m\.?)(?![a-z])/iu', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $x) {
                $h = (int) $x[1] % 12;
                if (stripos($x[3], 'p') === 0) $h += 12;
                if ((int) $x[1] <= 12) $times[] = sprintf('%02d:%02d', $h, (int) ($x[2] ?? 0));
            }
        }
        if (!$times && preg_match_all('/\b([01]?\d|2[0-3]):([0-5]\d)\s*(hrs|hours|h|ist)?\b/iu', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $x) $times[] = sprintf('%02d:%02d', (int) $x[1], (int) $x[2]);
        }
        // "6 - 9 PM": the first number inherits the meridiem of the second
        if (count($times) === 1 && preg_match('/\b(\d{1,2})\s*[-–to]+\s*\d{1,2}(?:[:\.]\d{2})?\s*(p\.?m\.?)/iu', $text, $r) && (int) $r[1] < 12) {
            array_unshift($times, sprintf('%02d:00', (int) $r[1] + 12));
        }
        return [$times[0] ?? null, $times[1] ?? null];
    }

    /** @return array{venue:?string, address:string}|null */
    private function extractLocation(string $text, ?string $placeText): ?array
    {
        if ($placeText !== null && trim($placeText) !== '') {
            return ['venue' => mb_substr(trim($placeText), 0, 200), 'address' => trim($placeText)];
        }
        if (preg_match('/(?:📍|🏢|\bvenue\s*[:\-–]|\blocation\s*[:\-–]|\bwhere\s*[:\-–]|\baddress\s*[:\-–]|\bplace\s*[:\-–])\s*(.{4,200})$/imu', $text, $m)) {
            $line = trim(preg_replace(['/#\S+/u', '/^(venue|location|where|address|place)\s*[:\-–]\s*/iu'], '', trim($m[1])));
            if (mb_strlen($line) >= 4) {
                $venue = trim(explode(',', $line)[0]);
                return ['venue' => mb_substr($venue, 0, 200), 'address' => mb_substr($line, 0, 500)];
            }
        }
        return null;
    }

    private function registrationUrl(string $text, array $urls): ?string
    {
        preg_match_all('#https?://[^\s<>"\')\]]+#iu', $text, $m);
        $all = array_values(array_unique(array_merge($urls, $m[0] ?? [])));
        $all = array_values(array_filter(array_map(fn($u) => rtrim($u, '.,;:!?'), $all), [UrlValidator::class, 'isAcceptable']));
        foreach ($all as $u) {
            if (preg_match(self::REG_HOSTS, $u)) return $u;
        }
        return null; // a random first link is not assumed to be the registration page
    }

    /** @return array{free:bool, from:?float} */
    private function price(string $text): array
    {
        if (preg_match('/\b(free (entry|registration|event|of cost|for all)|entry\s*[:\-]?\s*free|no (entry )?fee)\b/iu', $text)) {
            return ['free' => true, 'from' => null];
        }
        if (preg_match('/(?:₹|rs\.?|inr)\s?(\d[\d,]*)/iu', $text, $m)) {
            return ['free' => false, 'from' => (float) str_replace(',', '', $m[1])];
        }
        return ['free' => false, 'from' => null];
    }

    public function title(string $text): ?string
    {
        foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim(preg_replace(['/#\S+/u', '/https?:\/\/\S+/u', '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]/u', '/\s+/u'], ['', '', '', ' '], $line), " \t-–—:|*!");
            // Single-line posts ("X Meetup is on Oct 11 at …"): keep the name part only
            $line = trim(preg_split('/\s+(?:is|are)\s+(?:on|happening|back|coming)\b|\s+on\s+(?=(?:mon|tue|wed|thu|fri|sat|sun|jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\b|\d)|\s+[|•–—]\s+|[.!?](?:\s|$)/iu', $line)[0]);
            if (mb_strlen($line) >= 8) {
                return mb_substr($line, 0, 150);
            }
        }
        return null;
    }

    private function addDate(array &$found, int $day, int $month, string $year, \DateTimeImmutable $postedAt, bool $ambiguous): void
    {
        $inferred = $year === '';
        $y = $inferred ? (int) $postedAt->format('Y') : (int) $year;
        if (!checkdate($month, $day, $y)) {
            return;
        }
        $d = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $month, $day), new \DateTimeZone('Asia/Kolkata'));
        if ($inferred && $d < $postedAt->modify('-30 days')) {
            $d = $d->modify('+1 year');   // "5 Jan" posted in December means next January
        }
        $key = $d->format('Y-m-d');
        $found[$key] = [
            'year_inferred' => ($found[$key]['year_inferred'] ?? true) && $inferred,
            'ambiguous'     => ($found[$key]['ambiguous'] ?? false) || $ambiguous,
        ];
    }

    private function month(string $name): int
    {
        return self::MONTHS[substr(strtolower($name), 0, 4) === 'sept' ? 'sept' : substr(strtolower($name), 0, 3)] ?? 0;
    }

    private function parsePosted(?string $ts): \DateTimeImmutable
    {
        try {
            return $ts ? (new \DateTimeImmutable($ts))->setTimezone(new \DateTimeZone('Asia/Kolkata'))
                       : new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata'));
        } catch (\Throwable) {
            return new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata'));
        }
    }

    private function clean(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace("/[ \t]+/u", ' ', $text));
    }
}
