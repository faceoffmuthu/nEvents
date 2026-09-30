<?php

declare(strict_types=1);

namespace NEvents\Helpers;

/**
 * Event descriptions come from people and from discovered pages (Meetup and
 * others write Markdown: **bold**, ### headings, "- " lists). Everything is
 * HTML-escaped FIRST; only this small Markdown subset is then turned back
 * into markup, and links are allowed to http(s) only.
 */
final class RichText
{
    /** Safe HTML for an event description. */
    public static function toHtml(string $text): string
    {
        $text  = str_replace(["\r\n", "\r"], "\n", trim($text));
        $lines = explode("\n", htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $html  = '';
        $list  = false;
        $para  = [];

        $flushPara = function () use (&$para, &$html) {
            if ($para) { $html .= '<p>' . implode('<br>', $para) . '</p>'; $para = []; }
        };
        $closeList = function () use (&$list, &$html) {
            if ($list) { $html .= '</ul>'; $list = false; }
        };

        foreach ($lines as $raw) {
            $line = trim($raw);
            if ($line === '' || preg_match('/^(#{1,6}|[-*_]{3,})$/', $line)) {   // blank, bare "###", "---"
                $flushPara(); $closeList();
                continue;
            }
            if (preg_match('/^#{1,6}\s+(.+)$/', $line, $m)) {
                $flushPara(); $closeList();
                $html .= '<h3>' . self::inline($m[1]) . '</h3>';
                continue;
            }
            if (preg_match('/^(?:[-*•]|\d+[.)])\s+(.+)$/u', $line, $m)) {
                $flushPara();
                if (!$list) { $html .= '<ul>'; $list = true; }
                $html .= '<li>' . self::inline($m[1]) . '</li>';
                continue;
            }
            $closeList();
            $para[] = self::inline($line);
        }
        $flushPara(); $closeList();
        return $html;
    }

    /** Plain text (no Markdown symbols), optionally cut at a word boundary with "…". */
    public static function plain(string $text, ?int $limit = null): string
    {
        $t = preg_replace('/\[([^\]]+)\]\((?:https?:\/\/[^)\s]+)\)/', '$1', $text);   // [label](url) -> label
        $t = preg_replace('/^\s*#{1,6}\s*/m', '', (string) $t);
        $t = preg_replace('/(\*\*|__|\*|`)/', '', (string) $t);
        $t = preg_replace('/\\\\([\\\\`*_{}\[\]()#+\-.!])/', '$1', (string) $t);      // \- \* escapes
        $t = trim(preg_replace('/\s+/u', ' ', (string) $t));
        if ($limit !== null && mb_strlen($t) > $limit) {
            $cut = mb_substr($t, 0, $limit);
            $sp  = mb_strrpos($cut, ' ');
            $t   = rtrim(mb_substr($cut, 0, $sp !== false && $sp > $limit * .6 ? $sp : $limit), ' ,.;:-') . '…';
        }
        return $t;
    }

    /** Inline Markdown on already-escaped text. */
    private static function inline(string $s): string
    {
        $s = preg_replace('/\\\\([\\\\`*_{}\[\]()#+\-.!])/', '$1', $s);                       // Markdown escapes
        $s = preg_replace('/\*\*(.+?)\*\*|__(.+?)__/', '<strong>$1$2</strong>', (string) $s);
        $s = str_replace('**', '', (string) $s);                                                   // unpaired (cut-off text)
        $s = preg_replace('/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])/', '<em>$1</em>', (string) $s);
        $s = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/', function ($m) {
            return '<a href="' . $m[2] . '" target="_blank" rel="noopener noreferrer nofollow ugc">' . $m[1] . '</a>';
        }, (string) $s);
        return (string) $s;
    }
}
