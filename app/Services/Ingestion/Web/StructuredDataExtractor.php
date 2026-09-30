<?php

declare(strict_types=1);

namespace NEvents\Services\Ingestion\Web;

/**
 * Finds schema.org Event data in an HTML page and turns it into candidate
 * records. Shared by every keyless web adapter (single page, crawler,
 * sitemap, RSS item pages, search hits).
 *
 * Sources of truth, in order:
 *   1. JSON-LD <script type="application/ld+json"> — searched recursively,
 *      so Events inside @graph, ItemList/itemListElement, "item", "subEvent"
 *      etc. are all found.
 *   2. Microdata (itemscope itemtype="https://schema.org/Event" …) — mapped to
 *      the same shape as JSON-LD so one normalizer handles both.
 *
 * Only explicit structured data becomes a candidate; free text on a page is
 * never guessed into an event here.
 */
class StructuredDataExtractor
{
    /** @return array<int, array> raw event objects (JSON-LD shape) */
    public function extract(string $html): array
    {
        $events = [];

        if (preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $m)) {
            foreach ($m[1] as $json) {
                $data = json_decode(trim(html_entity_decode($json, ENT_NOQUOTES | ENT_HTML5, 'UTF-8')), true);
                if ($data === null) {
                    $data = json_decode(trim($json), true);
                }
                if (is_array($data)) {
                    $this->collectEvents($data, $events);
                }
            }
        }

        if (stripos($html, 'itemscope') !== false) {
            foreach ($this->microdataEvents($html) as $ev) {
                $events[] = $ev;
            }
        }

        // The same event can appear in JSON-LD and microdata on one page
        $seen = [];
        return array_values(array_filter($events, function (array $ev) use (&$seen) {
            $key = mb_strtolower(trim((string) ($ev['name'] ?? ''))) . '|' . substr((string) ($ev['startDate'] ?? ''), 0, 16);
            if (isset($seen[$key])) return false;
            return $seen[$key] = true;
        }));
    }

    /** Normalizes raw event objects into pipeline candidate records. */
    public function normalize(array $events, string $pageUrl, int $sourceId, array $extra = []): array
    {
        $records = [];
        foreach ($events as $ev) {
            $loc = $ev['location'] ?? [];
            $org = $ev['organizer'] ?? [];
            $off = $ev['offers']   ?? [];
            if (is_array($off) && isset($off[0])) $off = $off[0];
            if (is_array($loc) && isset($loc[0]) && is_array($loc[0])) $loc = $loc[0];
            if (is_string($loc)) $loc = ['address' => $loc];           // "location": "Some Hall, Chennai"
            if (!is_array($loc)) $loc = [];
            if (is_array($org) && isset($org[0])) $org = $org[0];
            if (!is_array($off)) $off = [];
            $address = $loc['address'] ?? [];
            if (!is_string($address) && !is_array($address)) $address = [];
            $image = $ev['image'] ?? null;
            if (is_array($image) && isset($image[0])) $image = $image[0];
            $startRaw = is_string($ev['startDate'] ?? null) ? $ev['startDate'] : '';
            $status   = is_string($ev['eventStatus'] ?? null) ? $ev['eventStatus'] : '';
            $evUrl    = $this->str($ev, 'url');
            $evUrl    = $evUrl && filter_var($evUrl, FILTER_VALIDATE_URL) ? $evUrl : null;

            // Structured data with an explicit start time is high confidence;
            // a date-only startDate is kept for review rather than guessed.
            $confidence = str_contains($startRaw, 'T') || preg_match('/\d{1,2}:\d{2}/', $startRaw) ? 90 : 65;

            $records[] = $extra + [
                'source_id'        => $sourceId,
                'source_url'       => $evUrl ?? $pageUrl,
                // Keyed on the event's own URL when it has one, so the listing
                // page and the event's detail page describe ONE candidate.
                'external_id'      => substr(sha1(($evUrl ?? $pageUrl . '|' . ($this->str($ev, 'name') ?? '')) . '|' . substr($startRaw, 0, 10)), 0, 40),
                'confidence'       => $confidence,
                'review_reasons'   => $confidence < 80 ? ['date_without_time'] : [],
                'status_raw'       => preg_match('/(Cancelled|Postponed|Rescheduled)$/i', $status, $sm) ? strtolower($sm[1]) : null,
                'title'            => $this->str($ev, 'name'),
                'description'      => ($d = $this->str($ev, 'description')) ? trim(strip_tags($d)) : null,
                'start_datetime'   => $this->toLocal($ev['startDate'] ?? null),
                'end_datetime'     => $this->toLocal($ev['endDate']   ?? null),
                'venue_name'       => $this->str($loc, 'name'),
                'venue_address'    => is_string($address) ? trim($address) : $this->joinAddress($address),
                'city_raw'         => is_array($address) ? $this->str($address, 'addressLocality') : null,
                'region_raw'       => is_array($address) ? $this->str($address, 'addressRegion') : null,
                'country_raw'      => is_array($address) ? ($this->str($address, 'addressCountry')
                                       ?? (is_array($address['addressCountry'] ?? null) ? $this->str($address['addressCountry'], 'name') : null)) : null,
                'event_type_raw'   => preg_replace('#^https?://schema\.org/#i', '', (string) (is_array($ev['@type'] ?? null) ? ($ev['@type'][0] ?? '') : ($ev['@type'] ?? ''))) ?: null,
                'organizer_name'   => is_string($org) ? $org : (is_array($org) ? $this->str($org, 'name') : null),
                'registration_url' => $this->str($off, 'url') ?? $evUrl,
                'start_utc_offset' => preg_match('/(Z|[+-]\d{2}:?\d{2})$/', trim($startRaw), $om) ? ($om[1] === 'Z' ? '+00:00' : $om[1]) : null,
                'price_currency'   => $this->str($off, 'priceCurrency'),
                'is_free'          => ($off['price'] ?? null) === 0 || ($off['price'] ?? null) === '0' || ($ev['isAccessibleForFree'] ?? null) === true || ($ev['isAccessibleForFree'] ?? null) === 'true' ? 1 : null,
                'price_from'       => is_numeric($off['price'] ?? null) && (float) $off['price'] > 0 ? (float) $off['price'] : null,
                'image_url'        => $this->absolute(is_string($image) ? $image : (is_array($image) && is_string($image['url'] ?? null) ? $image['url'] : null), $pageUrl),
                'format_raw'       => str_ends_with((string) ($ev['eventAttendanceMode'] ?? ''), 'OnlineEventAttendanceMode') ? 'online'
                                       : (str_ends_with((string) ($ev['eventAttendanceMode'] ?? ''), 'MixedEventAttendanceMode') ? 'hybrid' : null),
                'fetched_at'       => date('Y-m-d H:i:s'),
            ];
        }
        return $records;
    }

    public static function isEventType(mixed $type): bool
    {
        foreach ((array) $type as $t) {
            if (is_string($t) && preg_match('#^(https?://schema\.org/)?[A-Za-z]*Event$#', $t)) {
                return true;
            }
        }
        return false;
    }

    private function collectEvents(array $node, array &$out, int $depth = 0): void
    {
        if ($depth > 8) return;
        if (isset($node['@type']) && self::isEventType($node['@type']) && !empty($node['name'])) {
            $out[] = $node;
        }
        foreach ($node as $key => $value) {
            if (is_array($value) && $key !== 'location' && $key !== 'organizer' && $key !== 'offers') {
                $this->collectEvents($value, $out, $depth + 1);
            }
        }
    }

    // ------------------------------------------------------------------
    // Microdata
    // ------------------------------------------------------------------

    private function microdataEvents(string $html): array
    {
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xp = new \DOMXPath($doc);
        $events = [];
        foreach ($xp->query('//*[@itemscope][@itemtype]') as $el) {
            /** @var \DOMElement $el */
            $type = trim((string) $el->getAttribute('itemtype'));
            if (!self::isEventType(preg_replace('#^https?://schema\.org/#i', '', $type))) {
                continue;
            }
            // top-level events only (a nested Event is picked up as subEvent of its parent by JSON-LD rules)
            $item = $this->microdataItem($el);
            $item['@type'] = preg_replace('#^https?://schema\.org/#i', '', $type);
            if (!empty($item['name'])) {
                $events[] = $item;
            }
        }
        return $events;
    }

    private function microdataItem(\DOMElement $scope, int $depth = 0): array
    {
        $item = [];
        if ($depth > 4) return $item;
        foreach ($this->propertyElements($scope) as $prop) {
            /** @var \DOMElement $prop */
            $value = $prop->hasAttribute('itemscope') ? $this->microdataItem($prop, $depth + 1) : $this->propValue($prop);
            foreach (preg_split('/\s+/', trim($prop->getAttribute('itemprop'))) as $name) {
                if ($name !== '' && !isset($item[$name])) {
                    $item[$name] = $value;
                }
            }
        }
        return $item;
    }

    /** itemprop descendants that belong to THIS scope (not to a nested itemscope). */
    private function propertyElements(\DOMElement $scope): array
    {
        $out = [];
        $walk = function (\DOMNode $node) use (&$walk, &$out) {
            foreach ($node->childNodes as $child) {
                if (!$child instanceof \DOMElement) continue;
                if ($child->hasAttribute('itemprop')) {
                    $out[] = $child;
                }
                if (!$child->hasAttribute('itemscope')) {
                    $walk($child);
                }
            }
        };
        $walk($scope);
        return $out;
    }

    private function propValue(\DOMElement $el): string
    {
        $tag = strtolower($el->tagName);
        $v = match (true) {
            $el->hasAttribute('content')                                  => $el->getAttribute('content'),
            $tag === 'time' && $el->hasAttribute('datetime')              => $el->getAttribute('datetime'),
            in_array($tag, ['a', 'link', 'area'], true)                   => $el->getAttribute('href'),
            in_array($tag, ['img', 'audio', 'video', 'source', 'embed', 'iframe'], true) => $el->getAttribute('src'),
            in_array($tag, ['meta'], true)                                => $el->getAttribute('content'),
            in_array($tag, ['data', 'meter'], true)                       => $el->getAttribute('value'),
            default                                                       => $el->textContent,
        };
        return trim(preg_replace('/\s+/u', ' ', (string) $v));
    }

    // ------------------------------------------------------------------

    private function str(array $arr, string $key): ?string
    {
        $v = $arr[$key] ?? null;
        if (is_array($v) && isset($v['@value']) && is_string($v['@value'])) $v = $v['@value'];
        return is_string($v) ? (trim($v) ?: null) : (is_numeric($v) ? (string) $v : null);
    }

    private function joinAddress(array $a): ?string
    {
        $parts = array_filter([$this->str($a, 'streetAddress'), $this->str($a, 'addressLocality'), $this->str($a, 'addressRegion'), $this->str($a, 'postalCode')]);
        return $parts ? implode(', ', array_unique($parts)) : null;
    }

    /**
     * Local (Asia/Kolkata) wall-clock time, which the pipeline expects. An
     * explicit offset in the source ("+05:30", "Z") is honoured by converting.
     */
    private function toLocal(mixed $val): ?string
    {
        if (!is_string($val) || trim($val) === '') return null;
        try {
            return (new \DateTimeImmutable(trim($val), new \DateTimeZone('Asia/Kolkata')))
                ->setTimezone(new \DateTimeZone('Asia/Kolkata'))
                ->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /** A page-relative link ("/images/x.webp", "//cdn/x.webp") made absolute against the page it came from. */
    private function absolute(?string $url, string $pageUrl): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || preg_match('#^https?://#i', $url)) {
            return $url !== '' ? $url : null;
        }
        $p = parse_url($pageUrl);
        if (empty($p['host'])) {
            return null;
        }
        $scheme = $p['scheme'] ?? 'https';
        if (str_starts_with($url, '//')) {
            return $scheme . ':' . $url;
        }
        $base = $scheme . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        return str_starts_with($url, '/') ? $base . $url : $base . rtrim(dirname($p['path'] ?? '/') , '/') . '/' . $url;
    }
}
