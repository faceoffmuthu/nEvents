<?php

declare(strict_types=1);

namespace NEvents\Controllers\Public;

use NEvents\Core\Request;
use NEvents\Core\Response;
use NEvents\Core\View;
use NEvents\Core\Database\Connection;
use NEvents\Helpers\Slug;
use NEvents\Repositories\EventRepository;

/**
 * Organizers are taken from the events themselves: the linked organizer's name,
 * else the organizer name on the event (discovered events carry only a name).
 * Pages are keyed by the slug of that name.
 */
class OrganizerController
{
    public function __construct(private Connection $db, private EventRepository $events) {}

    public function directory(Request $request): Response
    {
        return View::make('organizers/index', [
            'title'      => 'Event Organizers in India',
            'organizers' => array_values($this->upcomingOrganizers()),
        ]);
    }

    public function profile(Request $request): Response
    {
        $slug      = $request->param('slug') ?? '';
        $organizer = $this->upcomingOrganizers()[$slug] ?? null;

        if (!$organizer) {
            http_response_code(404);
            return View::make('errors/404', ['title' => 'Organizer Not Found']);
        }

        $events = $this->events->findPublished(['organizer_names' => $organizer['names'], 'sort' => 'soonest'], 1, 48);
        $organizer['website_url'] = $organizer['website_url'] ?: ($events[0]['organizer_website'] ?? null);

        return View::make('organizers/show', [
            'title'     => $organizer['name'] . ' — N Events',
            'organizer' => $organizer,
            'events'    => $events,
        ]);
    }

    /**
     * Organizers with upcoming published events, keyed by slug, most events first.
     * Names that differ only in case, spacing or punctuation share one page.
     */
    private function upcomingOrganizers(): array
    {
        $rows = $this->db->select(
            "SELECT TRIM(COALESCE(o.name, e.organizer_display_name)) AS name,
                    MAX(o.logo_url) AS logo_url, MAX(o.description) AS description, MAX(o.website) AS website_url,
                    COUNT(DISTINCT e.id) AS event_count
               FROM events e
               JOIN event_occurrences eo ON eo.event_id = e.id
                    AND eo.status = 'scheduled' AND eo.start_at_utc >= UTC_TIMESTAMP()
               LEFT JOIN organizers o ON o.id = e.primary_organizer_id
              WHERE e.status = 'published'
                AND TRIM(COALESCE(o.name, e.organizer_display_name, '')) <> ''
              GROUP BY LOWER(TRIM(COALESCE(o.name, e.organizer_display_name)))
              ORDER BY event_count DESC, name ASC"
        );

        $orgs = [];
        foreach ($rows as $r) {
            $slug = Slug::make($r['name']);
            if (isset($orgs[$slug])) {
                $orgs[$slug]['names'][]      = $r['name'];
                $orgs[$slug]['event_count'] += (int) $r['event_count'];
                continue;
            }
            $orgs[$slug] = $r + ['slug' => $slug, 'names' => [$r['name']]];
        }
        uasort($orgs, fn ($a, $b) => [$b['event_count'], $a['name']] <=> [$a['event_count'], $b['name']]);

        return $orgs;
    }
}
