<?php

declare(strict_types=1);

namespace NEvents\Controllers\Public;

use NEvents\Core\Request;
use NEvents\Core\Response;
use NEvents\Core\View;
use NEvents\Repositories\EventRepository;
use NEvents\Repositories\CategoryRepository;
use NEvents\Repositories\CityRepository;
use NEvents\Services\Events\RegistrationRedirectService;
use NEvents\Services\Events\CalendarService;
use NEvents\Services\Location\DistrictService;
use NEvents\Services\Moderation\ModerationService;

class EventController
{
    public function __construct(
        private EventRepository            $events,
        private CategoryRepository         $categories,
        private CityRepository             $cities,
        private DistrictService            $districts,
        private RegistrationRedirectService $redirectService,
        private CalendarService            $calendarService,
        private View                       $view,
        private ModerationService          $moderation,
    ) {}

    /** Active district: explicit ?district= query param, else the visitor's chosen district, else null (All India). */
    private function activeDistrictId(Request $request): ?int
    {
        $explicit = (int) $request->query('district');
        if ($explicit > 0) {
            return $explicit;
        }
        return $this->districts->activeDistrict($request->isLoggedIn() ? $request->userId() : null)['id'] ?? null;
    }

    public function discover(Request $request): Response
    {
        $filters = $this->buildFilters($request);
        $page    = max(1, (int)$request->query('page', 1));

        $eventList = $this->events->findPublished($filters, $page, 20);
        $total     = $this->events->countPublished($filters);

        return $this->view->makeResponse('events.discover', [
            'title'      => 'Discover Events — N Events',
            'events'     => $eventList,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => 20,
            'filters'    => $filters,
            // the request's own filter parameters (URL names), for chip / pagination links
            'query'      => $this->filterQuery($request),
            'categories' => $this->categories->topLevel(),
            'states'     => $this->districts->getStates(),
            // districts of the chosen state only (the picker lists a state's districts)
            'districts'  => !empty($filters['state_id']) ? $this->districts->getDistrictsByState((int) $filters['state_id']) : [],
            'district'   => !empty($filters['district_id']) ? $this->districts->findById((int) $filters['district_id']) : null,
        ]);
    }

    public function today(Request $request): Response
    {
        $districtId = $this->activeDistrictId($request);
        $now    = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata'));
        $filters = [
            'district_id' => $districtId,
            'date_from'   => $now->format('Y-m-d 00:00:00'),
            'date_to'     => $now->format('Y-m-d 23:59:59'),
        ];
        $eventList = $this->events->findPublished($filters, 1, 30);

        return $this->view->makeResponse('events.listing', [
            'title'      => 'Events Today — N Events',
            'heading'    => 'Events Today',
            'events'     => $eventList,
            'total'      => count($eventList),
            'filters'    => $filters,
            'categories' => $this->categories->topLevel(),
        ]);
    }

    public function tomorrow(Request $request): Response
    {
        $districtId = $this->activeDistrictId($request);
        $tz     = new \DateTimeZone('Asia/Kolkata');
        $tom    = (new \DateTimeImmutable('tomorrow', $tz));
        $filters = [
            'district_id' => $districtId,
            'date_from'   => $tom->format('Y-m-d 00:00:00'),
            'date_to'     => $tom->format('Y-m-d 23:59:59'),
        ];
        return $this->view->makeResponse('events.listing', [
            'title'   => 'Events Tomorrow — N Events',
            'heading' => 'Events Tomorrow',
            'events'  => $this->events->findPublished($filters, 1, 30),
            'total'   => 0,
            'filters' => $filters,
            'categories' => $this->categories->topLevel(),
        ]);
    }

    public function weekend(Request $request): Response
    {
        $districtId = $this->activeDistrictId($request);
        return $this->view->makeResponse('events.listing', [
            'title'   => 'Events This Weekend — N Events',
            'heading' => 'This Weekend',
            'events'  => $this->events->getThisWeekend($districtId),
            'total'   => 0,
            'filters' => [],
            'categories' => $this->categories->topLevel(),
        ]);
    }

    public function thisWeek(Request $request): Response
    {
        $districtId = $this->activeDistrictId($request);
        $tz     = new \DateTimeZone('Asia/Kolkata');
        $start  = new \DateTimeImmutable('monday this week', $tz);
        $end    = new \DateTimeImmutable('sunday this week', $tz);
        $filters = [
            'district_id' => $districtId,
            'date_from'   => $start->format('Y-m-d 00:00:00'),
            'date_to'     => $end->format('Y-m-d 23:59:59'),
        ];
        return $this->view->makeResponse('events.listing', [
            'title'   => 'Events This Week — N Events',
            'heading' => 'This Week',
            'events'  => $this->events->findPublished($filters, 1, 30),
            'total'   => 0,
            'filters' => $filters,
            'categories' => $this->categories->topLevel(),
        ]);
    }

    public function freeEvents(Request $request): Response
    {
        $districtId = $this->activeDistrictId($request);
        $filters = ['district_id' => $districtId, 'free' => true];
        return $this->view->makeResponse('events.listing', [
            'title'   => 'Free Events — N Events',
            'heading' => 'Free Events',
            'events'  => $this->events->findPublished($filters, 1, 30),
            'total'   => 0,
            'filters' => $filters,
            'categories' => $this->categories->topLevel(),
        ]);
    }

    public function onlineEvents(Request $request): Response
    {
        $filters = ['format' => 'online'];
        return $this->view->makeResponse('events.listing', [
            'title'   => 'Online Events — N Events',
            'heading' => 'Online Events',
            'events'  => $this->events->findPublished($filters, 1, 30),
            'total'   => 0,
            'filters' => $filters,
            'categories' => $this->categories->topLevel(),
        ]);
    }

    public function nearby(Request $request): Response
    {
        $districtId = $this->activeDistrictId($request);
        $filters = ['district_id' => $districtId];
        return $this->view->makeResponse('events.listing', [
            'title'   => 'Nearby Events — N Events',
            'heading' => 'Events Near You',
            'events'  => $this->events->findPublished($filters, 1, 30),
            'total'   => 0,
            'filters' => $filters,
            'categories' => $this->categories->topLevel(),
        ]);
    }

    public function featured(Request $request): Response
    {
        $districtId = $this->activeDistrictId($request);
        return $this->view->makeResponse('events.listing', [
            'title'   => 'Featured Events — N Events',
            'heading' => 'Featured Events',
            'events'  => $this->events->getFeatured($districtId, 20),
            'total'   => 0,
            'filters' => [],
            'categories' => $this->categories->topLevel(),
        ]);
    }

    /**
     * SEO district landing page — /events/{slug}. Resolves against the
     * districts table first (the primary, shareable URL for a whole
     * district, e.g. /events/chennai), falling back to a specific
     * locality/city slug (e.g. /events/anna-nagar) for a narrower view.
     */
    public function cityPage(Request $request): Response
    {
        $slug = $request->param('city');

        $district = $this->districts->findBySlug($slug);
        if ($district) {
            $filters = ['district_id' => $district['id']];
            $events  = $this->events->findPublished($filters, 1, 20);
            return $this->view->makeResponse('events.city', [
                'title'   => 'Events in ' . $district['name'] . ' — N Events',
                'city'    => $district,
                'events'  => $events,
                'total'   => $this->events->countPublished($filters),
                'categories' => $this->categories->topLevel(),
            ]);
        }

        $city = $this->cities->findBySlug($slug);
        if ($city) {
            $filters = ['city_id' => $city['id']];
            $events  = $this->events->findPublished($filters, 1, 20);
            return $this->view->makeResponse('events.city', [
                'title'   => 'Events in ' . $city['name'] . ' — N Events',
                'city'    => $city,
                'events'  => $events,
                'total'   => $this->events->countPublished($filters),
                'categories' => $this->categories->topLevel(),
            ]);
        }

        return $this->view->makeResponse('errors.404', ['title' => '404 — Location Not Found'], 404);
    }

    public function cityCategoryPage(Request $request): Response
    {
        $slug     = $request->param('city');
        $catSlug  = $request->param('category');
        $category = $this->categories->findBySlug($catSlug);

        if (!$category) {
            return $this->view->makeResponse('errors.404', ['title' => '404 — Not Found'], 404);
        }

        $district = $this->districts->findBySlug($slug);
        if ($district) {
            $filters = ['district_id' => $district['id'], 'category_id' => $category['id']];
            $events  = $this->events->findPublished($filters, 1, 20);
            return $this->view->makeResponse('events.city-category', [
                'title'    => $category['name'] . ' Events in ' . $district['name'] . ' — N Events',
                'city'     => $district,
                'category' => $category,
                'events'   => $events,
                'total'    => $this->events->countPublished($filters),
                'categories' => $this->categories->topLevel(),
            ]);
        }

        $city = $this->cities->findBySlug($slug);
        if (!$city) {
            return $this->view->makeResponse('errors.404', ['title' => '404 — Not Found'], 404);
        }

        $filters = ['city_id' => $city['id'], 'category_id' => $category['id']];
        $events  = $this->events->findPublished($filters, 1, 20);

        return $this->view->makeResponse('events.city-category', [
            'title'    => $category['name'] . ' Events in ' . $city['name'] . ' — N Events',
            'city'     => $city,
            'category' => $category,
            'events'   => $events,
            'total'    => $this->events->countPublished($filters),
            'categories' => $this->categories->topLevel(),
        ]);
    }

    public function categoryPage(Request $request): Response
    {
        $slug     = $request->param('slug');
        $category = $this->categories->findBySlug($slug);
        if (!$category) {
            return $this->view->makeResponse('errors.404', ['title' => '404'], 404);
        }

        $districtId = $this->activeDistrictId($request);
        $filters = ['category_id' => $category['id'], 'district_id' => $districtId];
        $events  = $this->events->findPublished($filters, 1, 20);

        return $this->view->makeResponse('events.category', [
            'title'       => $category['name'] . ' Events — N Events',
            'category'    => $category,
            'events'      => $events,
            'total'       => $this->events->countPublished($filters),
            'subcategories' => $this->categories->getSubcategories($category['id']),
            'categories'  => $this->categories->topLevel(),
        ]);
    }

    public function show(Request $request): Response
    {
        $slug  = $request->param('slug');
        $event = $this->events->findBySlug($slug);

        if (!$event) {
            return $this->view->makeResponse('errors.404', ['title' => '404 — Event Not Found'], 404);
        }

        $this->events->incrementViews($event['id']);

        $sessionId = session_id();
        $this->events->recordInteraction($request->userId(), $event['id'], 'view', $sessionId, $request->getIp());

        $occurrences = $this->events->getOccurrences($event['id']);
        $eventCats   = $this->events->getCategories($event['id']);
        $catIds      = array_column($eventCats, 'id');
        $tags        = $this->events->getTags($event['id']);
        $related     = $this->events->getRelated($event['id'], $event['city_id'] ?? 1, $catIds, 4);
        $isSaved     = $request->isLoggedIn() ? $this->events->isEventSavedByUser($request->userId(), $event['id']) : false;
        $gcalUrl     = $this->buildGCalUrl($event, $occurrences[0] ?? null);
        $regState    = $this->redirectService->determineState($event, $occurrences);

        return $this->view->makeResponse('events.show', [
            'title'       => $event['title'] . ' — N Events',
            'meta_desc'   => \NEvents\Helpers\RichText::plain((string) ($event['short_summary'] ?? ''), 160),
            'event'       => $event,
            'occurrences' => $occurrences,
            'categories'  => $eventCats,
            'tags'        => $tags,
            'related'     => $related,
            'is_saved'    => $isSaved,
            'gcal_url'    => $gcalUrl,
            'reg_state'   => $regState,
            'source_url'  => $this->events->getPrimarySourceUrl((int) $event['id']),
        ]);
    }

    public function registerRedirect(Request $request): Response
    {
        $slug  = $request->param('slug');
        $event = $this->events->findBySlug($slug);
        if (!$event) return $this->view->makeResponse('errors.404', ['title' => '404'], 404);

        $occurrences = $this->events->getOccurrences($event['id']);
        $state = $this->redirectService->determineState($event, $occurrences);

        if ($state === 'no_link') {
            return $this->view->makeResponse('events.no-registration', [
                'title' => 'Registration — N Events',
                'event' => $event,
            ]);
        }
        if ($state === 'cancelled' || $state === 'completed') {
            return $this->view->makeResponse('events.registration-closed', [
                'title' => ($state === 'cancelled' ? 'Event Cancelled' : 'Event Completed') . ' — N Events',
                'event' => $event,
                'state' => $state,
            ]);
        }

        $url = $event['registration_url'];

        // SSRF protection: only allow http/https to non-private destinations
        if (!$this->redirectService->isSafeExternalUrl($url)) {
            return $this->view->makeResponse('events.bad-link', ['event' => $event, 'title' => 'Registration Link'], 400);
        }

        // Record click — never blocks the redirect if this fails
        try {
            $this->events->recordInteraction($request->userId(), $event['id'], 'register_click', session_id(), $request->getIp());
            $this->events->incrementClickCount($event['id']);
        } catch (\Throwable) {
            // analytics failure must never prevent the user from reaching registration
        }

        return $this->view->makeResponse('events.register-redirect', [
            'title'       => 'Redirecting to Registration — N Events',
            'event'       => $event,
            'external_url'=> $url,
        ]);
    }

    public function save(Request $request): Response
    {
        if (!$request->isLoggedIn()) {
            // After signing in, come back to the page the heart was tapped on (same site only)
            $ref = parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''));
            $sameSite = isset($ref['host']) && strtolower($ref['host'] . (isset($ref['port']) ? ':' . $ref['port'] : '')) === strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
            if ($sameSite && str_starts_with($ref['path'] ?? '', '/')) {
                $_SESSION['intended_url'] = $ref['path'] . (isset($ref['query']) ? '?' . $ref['query'] : '');
            }
            return Response::json(['success' => false, 'error' => 'Please log in to save events.'], 401);
        }
        $slug  = $request->param('slug');
        $event = $this->events->findBySlug($slug);
        if (!$event) return Response::json(['success' => false], 404);

        $this->events->saveEvent($request->userId(), $event['id']);
        $this->events->recordInteraction($request->userId(), $event['id'], 'save', session_id(), $request->getIp());

        return Response::json(['success' => true, 'action' => 'saved']);
    }

    public function unsave(Request $request): Response
    {
        if (!$request->isLoggedIn()) {
            return Response::json(['success' => false, 'error' => 'Please log in.'], 401);
        }
        $slug  = $request->param('slug');
        $event = $this->events->findBySlug($slug);
        if (!$event) return Response::json(['success' => false], 404);

        $this->events->unsaveEvent($request->userId(), $event['id']);
        return Response::json(['success' => true, 'action' => 'unsaved']);
    }

    public function report(Request $request): Response
    {
        if (!$request->isLoggedIn()) {
            return Response::json(['success' => false, 'error' => 'Please log in to report an event.'], 401);
        }
        $event = $this->events->findBySlug((string) $request->param('slug'));
        if (!$event) {
            return Response::json(['success' => false, 'error' => 'Event not found.'], 404);
        }

        $result = $this->moderation->report(
            (int) $event['id'],
            $request->userId(),
            (string) $request->post('reason', ''),
            (string) $request->post('details', ''),
            $request->getIp()
        );

        return Response::json(
            ['success' => $result['ok'], 'message' => $result['message'] ?? null, 'error' => $result['error'] ?? null],
            $result['ok'] ? 200 : 422
        );
    }

    public function icsDownload(Request $request): Response
    {
        $slug  = $request->param('slug');
        $event = $this->events->findBySlug($slug);
        if (!$event) return Response::html('Not found', 404);

        $occurrences = $this->events->getOccurrences($event['id']);
        $ics         = $this->calendarService->generateIcs($event, $occurrences);

        $response = new Response();
        $response->setStatus(200);
        $response->setHeader('Content-Type', 'text/calendar; charset=utf-8');
        $response->setHeader('Content-Disposition', 'attachment; filename="event.ics"');
        $response->setBody($ics);
        return $response;
    }

    private const FILTER_PARAMS = ['q', 'state', 'district', 'city', 'category', 'format', 'free', 'when', 'from', 'to', 'sort'];

    private function buildFilters(Request $request): array
    {
        $filters = [];
        $isDate  = fn($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);
        if ($request->query('state'))    $filters['state_id']    = (int)$request->query('state');
        if ($request->query('district')) $filters['district_id'] = (int)$request->query('district');
        // A district always belongs to one state: keep the state in step with it
        if (!empty($filters['district_id']) && ($d = $this->districts->findById($filters['district_id']))) {
            $filters['state_id'] = (int) $d['state_id'];
        }
        if ($request->query('city'))     $filters['city_id']     = (int)$request->query('city');
        if ($request->query('category')) $filters['category_id'] = (int)$request->query('category');
        if (in_array($request->query('format'), ['offline', 'online', 'hybrid'], true)) $filters['format'] = $request->query('format');
        if ($request->query('free'))     $filters['free']        = true;
        if ($isDate($request->query('from'))) $filters['date_from'] = $request->query('from') . ' 00:00:00';
        if ($isDate($request->query('to')))   $filters['date_to']   = $request->query('to')   . ' 23:59:59';
        if ($request->query('q'))        $filters['search']      = trim((string)$request->query('q'));
        if (in_array($request->query('sort'), ['soonest', 'newest', 'popular'], true)) $filters['sort'] = $request->query('sort');

        // Date presets (IST): today / tomorrow / weekend / week — override from/to
        $tz  = new \DateTimeZone('Asia/Kolkata');
        $now = new \DateTimeImmutable('now', $tz);
        $range = match ($request->query('when')) {
            'today'    => [$now, $now],
            'tomorrow' => [$now->modify('+1 day'), $now->modify('+1 day')],
            'weekend'  => (int) $now->format('N') >= 6
                            ? [$now, $now->modify('sunday this week')]
                            : [$now->modify('saturday this week'), $now->modify('sunday this week')],
            'week'     => [$now, $now->modify('sunday this week')],
            default    => null,
        };
        if ($range) {
            $filters['date_from'] = $range[0]->format('Y-m-d 00:00:00');
            $filters['date_to']   = $range[1]->format('Y-m-d 23:59:59');
            $filters['when']      = $request->query('when');
        }
        return $filters;
    }

    /** Current filter parameters under their URL names (empty ones dropped). */
    private function filterQuery(Request $request): array
    {
        $q = [];
        foreach (self::FILTER_PARAMS as $k) {
            $v = $request->query($k);
            if ($v !== null && $v !== '') $q[$k] = (string) $v;
        }
        return $q;
    }

    /**
     * Pre-filled "Add to Google Calendar" link (no OAuth needed). Times are
     * sent as UTC ("Z"), so Google shows them correctly in the viewer's own
     * timezone; ctz is a display hint for the event's local timezone.
     */
    private function buildGCalUrl(array $event, ?array $occurrence): string
    {
        if (!$occurrence) return '';
        $startTs = strtotime($occurrence['start_at_utc'] . ' UTC');
        $endTs   = $occurrence['end_at_utc'] ? strtotime($occurrence['end_at_utc'] . ' UTC') : $startTs + 7200;

        $location = ($event['format'] ?? '') === 'online'
            ? trim('Online' . (!empty($event['online_platform']) ? ' — ' . $event['online_platform'] : ''))
            : implode(', ', array_filter([
                $event['venue_name'] ?? null, $event['venue_address'] ?? null,
                $event['venue_locality'] ?? null, $event['district_name'] ?? ($event['city_name'] ?? null),
            ]));

        $details = trim(implode("\n\n", array_filter([
            $event['short_summary'] ?? '',
            !empty($event['description']) ? mb_strimwidth((string) $event['description'], 0, 800, '…') : '',
            !empty($event['registration_url']) ? 'Register: ' . $event['registration_url'] : '',
            'Event page: ' . View::url('event/' . $event['slug']),
        ])));

        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action'   => 'TEMPLATE',
            'text'     => $event['title'],
            'dates'    => gmdate('Ymd\THis\Z', $startTs) . '/' . gmdate('Ymd\THis\Z', $endTs),
            'details'  => $details,
            'location' => $location,
            'ctz'      => $event['timezone'] ?: 'Asia/Kolkata',
        ], '', '&', PHP_QUERY_RFC3986);
    }
}

