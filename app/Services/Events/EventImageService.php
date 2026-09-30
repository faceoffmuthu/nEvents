<?php

declare(strict_types=1);

namespace NEvents\Services\Events;

/**
 * Centralizes event image selection so every template (event cards, the
 * detail page hero, organizer pages, related-events lists) resolves images
 * the same way instead of repeating fallback logic.
 *
 * Priority:
 *   1. events.featured_image_url — covers both a source-provided image and
 *      an organizer-uploaded poster; this schema doesn't distinguish the two,
 *      so whichever populated the column wins.
 *   2. A category-specific fallback illustration (public/images/event-fallbacks/),
 *      keyed by the event's primary category slug, falling back to its parent
 *      category's artwork, then to a generic platform fallback. Never null,
 *      never a broken path — the fallback set always resolves to something.
 */
class EventImageService
{
    /** Category slugs (or parent slugs) that have dedicated fallback artwork. */
    private const AVAILABLE = [
        'technology', 'artificial-intelligence', 'software-development',
        'digital-marketing', 'marketing', 'startup', 'business', 'networking',
        'cycling', 'fitness', 'workshop', 'conference', 'career', 'culture',
    ];

    /** Subcategory slug -> parent slug, for categories without their own artwork. */
    private const PARENT_OF = [
        'machine-learning'     => 'artificial-intelligence',
        'data-science'         => 'artificial-intelligence',
        'cybersecurity'        => 'technology',
        'cloud-computing'      => 'technology',
        'devops'               => 'technology',
        'web-development'      => 'software-development',
        'mobile-development'   => 'software-development',
        'blockchain'           => 'technology',
        'seo'                  => 'digital-marketing',
        'social-media'         => 'digital-marketing',
        'branding'             => 'marketing',
        'content-marketing'    => 'digital-marketing',
        'founders'             => 'startup',
        'funding'              => 'startup',
        'pitching'             => 'startup',
        'incubation'           => 'startup',
        'msme'                 => 'business',
        'leadership'           => 'business',
        'sales'                => 'business',
        'finance'              => 'business',
        'running'              => 'fitness',
        'marathon'             => 'fitness',
        'yoga'                 => 'fitness',
        'expo'                 => 'conference',
        'education'            => 'career',
        'professional'         => 'business',
        'community'            => 'networking',
    ];

    /**
     * @param array $event Row from EventRepository — needs 'featured_image_url'
     *                      and, ideally, 'category_slug' (primary category).
     * @return array{url: string, alt: string, is_fallback: bool}
     */
    public static function resolve(array $event): array
    {
        $title = $event['title'] ?? 'Event';

        if (!empty($event['featured_image_url'])) {
            $url = $event['featured_image_url'];
            // Poster uploaded through /events/create is stored as a path
            // relative to public/ (e.g. uploads/events/2026/09/abc.jpg)
            if (!preg_match('#^(https?:)?//#i', $url) && !str_starts_with($url, '/')) {
                $url = \NEvents\Core\View::asset($url);
            }
            return [
                'url'         => $url,
                'alt'         => $title,
                'is_fallback' => false,
            ];
        }

        $slug = self::fallbackSlugFor($event['category_slug'] ?? null);

        return [
            // Built from APP_URL so it also works when the app lives under a sub-path (XAMPP)
            'url'         => \NEvents\Core\View::asset('images/event-fallbacks/' . $slug . '.svg'),
            'alt'         => $title . ' — ' . ($event['category_name'] ?? 'Event') . ' event artwork',
            'is_fallback' => true,
        ];
    }

    private static function fallbackSlugFor(?string $categorySlug): string
    {
        if ($categorySlug === null) {
            return 'default';
        }
        if (in_array($categorySlug, self::AVAILABLE, true)) {
            return $categorySlug;
        }
        $parent = self::PARENT_OF[$categorySlug] ?? null;
        if ($parent !== null && in_array($parent, self::AVAILABLE, true)) {
            return $parent;
        }
        return 'default';
    }
}
