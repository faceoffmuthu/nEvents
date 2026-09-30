<?php

declare(strict_types=1);

namespace NEvents\Services\Ingestion\Web;

/**
 * Which ONLINE discovered events N Events keeps (owner's rule, 2026-09-24):
 * hosted from India or Canada AND in English, Tamil, Hindi, Malayalam or
 * Telugu. An event whose country can't be determined is rejected (strict).
 *
 * Online events have no address, so the country is inferred from evidence,
 * strongest first:
 *   stated country (addressCountry) · Canadian / Indian places named in the text
 *   · price currency (INR / CAD) · named time zone (ICS TZID, e.g. America/Toronto)
 *   · UTC offset +05:30 (India only — Canadian offsets are shared with the USA,
 *     so an offset alone never proves Canada) · .ca / .in web addresses.
 * Language: Tamil / Devanagari (Hindi) / Malayalam / Telugu scripts are
 * recognised directly; Latin-script text counts as English only when common
 * English words clearly outnumber French / Spanish / German / Portuguese ones.
 */
class OnlineAudience
{
    public const COUNTRIES = ['IN', 'CA'];
    public const LANGUAGES = ['en', 'ta', 'hi', 'ml', 'te'];

    private const CA_PLACES = ['canada', 'ontario', 'quebec', 'québec', 'british columbia', 'alberta', 'manitoba', 'saskatchewan', 'nova scotia',
        'new brunswick', 'newfoundland', 'prince edward island', 'toronto', 'montreal', 'montréal', 'vancouver', 'calgary', 'edmonton', 'ottawa',
        'winnipeg', 'mississauga', 'brampton', 'scarborough', 'markham', 'halifax', 'kitchener'];   // ambiguous names (Hamilton, Surrey, Waterloo) left out
    /** Tamil Nadu / Puducherry places (IndiaLocation covers states and other big Indian cities) */
    private const TN_PLACES = ['chennai', 'madras', 'coimbatore', 'kovai', 'madurai', 'tiruchirappalli', 'trichy', 'tirunelveli', 'vellore',
        'erode', 'tiruppur', 'thoothukudi', 'tuticorin', 'kanchipuram', 'thanjavur', 'nagercoil', 'hosur', 'karur', 'dindigul', 'ooty',
        'puducherry', 'pondicherry'];
    private const CA_TIMEZONES = ['America/Toronto', 'America/Montreal', 'America/Vancouver', 'America/Edmonton', 'America/Winnipeg', 'America/Halifax',
        'America/St_Johns', 'America/Regina', 'America/Moncton', 'America/Whitehorse', 'America/Yellowknife', 'America/Iqaluit', 'Canada/Eastern',
        'Canada/Pacific', 'Canada/Central', 'Canada/Mountain', 'Canada/Atlantic', 'Canada/Newfoundland'];
    private const IN_TIMEZONES = ['Asia/Kolkata', 'Asia/Calcutta'];

    private const EN_WORDS = ['the', 'and', 'for', 'with', 'you', 'your', 'join', 'how', 'this', 'that', 'are', 'will', 'from', 'our', 'what',
        'learn', 'about', 'into', 'online', 'free', 'workshop', 'webinar', 'session', 'meetup', 'is', 'to', 'of', 'in', 'on', 'we', 'at', 'by'];
    private const OTHER_WORDS = ['le', 'la', 'les', 'des', 'du', 'et', 'pour', 'avec', 'une', 'dans', 'sur', 'est', 'vous', 'nous', 'en ligne',
        'el', 'los', 'las', 'del', 'para', 'con', 'una', 'por', 'und', 'der', 'die', 'das', 'mit', 'für', 'ein', 'eine', 'nicht', 'uma', 'não', 'com', 'você'];

    public function __construct(private IndiaLocation $india) {}

    /** @return array{allowed: bool, country: ?string, language: string, reason: ?string} */
    public function check(array $record): array
    {
        $country  = $this->country($record);
        $language = $this->language($record);
        $reasons  = [];
        if (!in_array($country, self::COUNTRIES, true)) $reasons[] = 'country_' . ($country ?? 'unknown');
        if (!in_array($language, self::LANGUAGES, true)) $reasons[] = 'language_' . $language;
        return ['allowed' => !$reasons, 'country' => $country, 'language' => $language,
                'reason' => $reasons ? 'online_outside_audience: ' . implode(', ', $reasons) : null];
    }

    public function country(array $record): ?string
    {
        $stated = strtolower(trim((string) ($record['country_raw'] ?? '')));
        if ($stated !== '') {
            return match (true) {
                in_array($stated, ['in', 'ind', 'india', 'bharat'], true) => 'IN',
                in_array($stated, ['ca', 'can', 'canada'], true)         => 'CA',
                default                                                  => strtoupper(substr($stated, 0, 2)),
            };
        }

        $text = mb_strtolower(implode(' | ', array_filter([$record['title'] ?? null, mb_substr((string) ($record['description'] ?? ''), 0, 1500),
            $record['organizer_name'] ?? null, $record['city_raw'] ?? null, $record['region_raw'] ?? null], 'is_string')));
        foreach (self::CA_PLACES as $p) {
            if (preg_match('/(?<![a-z])' . preg_quote($p, '/') . '(?![a-z])/u', $text)) return 'CA';
        }
        foreach (self::TN_PLACES as $p) {
            if (preg_match('/(?<![a-z])' . preg_quote($p, '/') . '(?![a-z])/u', $text)) return 'IN';
        }
        if ($this->india->classify(['venue_address' => $text])['in_india'] === true) return 'IN';

        $cur = strtoupper((string) ($record['price_currency'] ?? ''));
        if ($cur === 'INR') return 'IN';
        if ($cur === 'CAD') return 'CA';

        $tz = (string) ($record['start_tzid'] ?? '');
        if (in_array($tz, self::IN_TIMEZONES, true)) return 'IN';
        if (in_array($tz, self::CA_TIMEZONES, true)) return 'CA';

        if (in_array($record['start_utc_offset'] ?? null, ['+05:30', '+0530'], true)) return 'IN';

        foreach (['registration_url', 'source_url'] as $f) {
            $host = strtolower((string) parse_url((string) ($record[$f] ?? ''), PHP_URL_HOST));
            if (preg_match('/\.ca$/', $host)) return 'CA';
            if (preg_match('/\.(in|co\.in)$/', $host)) return 'IN';
        }
        return null;
    }

    /** 'en' | 'ta' | 'hi' | 'ml' | 'te' | 'other' */
    public function language(array $record): string
    {
        $text = trim(($record['title'] ?? '') . ' ' . mb_substr((string) ($record['description'] ?? ''), 0, 800));
        $scripts = ['ta' => '\p{Tamil}', 'hi' => '\p{Devanagari}', 'ml' => '\p{Malayalam}', 'te' => '\p{Telugu}'];
        $best = null; $bestN = 0;
        foreach ($scripts as $lang => $rx) {
            $n = preg_match_all('/' . $rx . '/u', $text);
            if ($n > $bestN) { $best = $lang; $bestN = $n; }
        }
        if ($best !== null && $bestN >= 3) return $best;

        $words = preg_split('/[^\p{L}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $en = count(array_filter($words, fn($w) => in_array($w, self::EN_WORDS, true)));
        $other = count(array_filter($words, fn($w) => in_array($w, self::OTHER_WORDS, true)));
        if (str_contains(mb_strtolower($text), 'en ligne')) $other += 2;
        return ($en >= 2 && $en > $other * 1.5) || ($en >= 1 && $other === 0 && count($words) <= 6) ? 'en' : 'other';
    }
}
