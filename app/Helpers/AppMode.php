<?php

declare(strict_types=1);

namespace NEvents\Helpers;

/**
 * Is this page being shown inside the Android / iOS app (mobile/)?
 * The app adds "NEventsApp" to its user agent (capacitor.config.json appendUserAgent);
 * the site then uses the app interface instead of the website one.
 *
 * Outside production, ?app_preview=1 shows the app interface in a normal browser
 * (remembered in a cookie; ?app_preview=0 turns it off) — for checking screens on a PC.
 */
final class AppMode
{
    private static ?bool $active = null;

    public static function active(): bool
    {
        if (self::$active !== null) {
            return self::$active;
        }
        if (str_contains((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 'NEventsApp')) {
            return self::$active = true;
        }
        if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
            return self::$active = false;
        }
        $preview = $_GET['app_preview'] ?? null;
        if ($preview !== null && !headers_sent()) {
            setcookie('ne_app_preview', $preview === '1' ? '1' : '', ['expires' => $preview === '1' ? 0 : 1, 'path' => '/', 'samesite' => 'Lax']);
        }
        return self::$active = $preview !== null ? $preview === '1' : (($_COOKIE['ne_app_preview'] ?? '') === '1');
    }
}
