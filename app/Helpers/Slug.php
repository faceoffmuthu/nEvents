<?php

declare(strict_types=1);

namespace NEvents\Helpers;

/** URL slugs for names (organizer pages are keyed by the organizer's name). */
final class Slug
{
    public static function make(string $text): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($text))), '-');
        // Names with no Latin letters or digits (e.g. Tamil-only) get a stable short hash
        return $slug !== '' ? $slug : 'o-' . substr(md5(mb_strtolower(trim($text))), 0, 8);
    }
}
