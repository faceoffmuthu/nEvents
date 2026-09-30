<?php

declare(strict_types=1);

namespace NEvents\Controllers\Api;

use NEvents\Core\Request;
use NEvents\Core\Response;
use NEvents\Repositories\EventRepository;
use NEvents\Repositories\CityRepository;
use NEvents\Repositories\CategoryRepository;
use NEvents\Repositories\UserRepository;
use NEvents\Services\Location\DistrictService;

class EventApiController
{
    public function __construct(
        private EventRepository    $events,
        private CityRepository     $cities,
        private CategoryRepository $categories,
        private DistrictService    $districts,
        private UserRepository     $users,
    ) {}

    public function list(Request $request): Response
    {
        $filters = [
            'district_id' => $request->query('district_id') ?: null,
            'city_id'     => $request->query('city_id')     ?: null,
            'category_id' => $request->query('category_id') ?: null,
            'format'      => $request->query('format')      ?: null,
            'free'        => $request->query('free')        ? true : false,
            'search'      => $request->query('q')           ?: null,
        ];
        $page    = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(5, (int) $request->query('per_page', 20)));

        $rows  = $this->events->findPublished($filters, $page, $perPage);
        $total = $this->events->countPublished($filters);

        return Response::json([
            'success'    => true,
            'data'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $perPage,
            'last_page'  => (int) ceil($total / max(1, $perPage)),
        ]);
    }

    public function autocomplete(Request $request): Response
    {
        $q = trim($request->query('q', ''));
        if (strlen($q) < 2) {
            return Response::json(['success' => true, 'results' => []]);
        }

        $results = $this->events->findPublished(['search' => $q], 1, 8);
        $appUrl  = rtrim($_ENV['APP_URL'] ?? '', '/');

        $out = array_map(fn($e) => [
            'title'    => $e['title'],
            'url'      => "{$appUrl}/event/{$e['slug']}",
            'category' => $e['category_name'] ?? '',
        ], $results);

        return Response::json(['success' => true, 'results' => $out]);
    }

    public function cities(Request $request): Response
    {
        $q    = trim($request->query('q', ''));
        $rows = $q ? $this->cities->searchByName($q) : $this->cities->featured();
        return Response::json(['success' => true, 'data' => $rows]);
    }

    /** GET /api/districts?state=ID — a state's districts; without a state, the states list. */
    public function districts(Request $request): Response
    {
        $stateId = (int) $request->query('state', 0);
        return Response::json([
            'success' => true,
            'data'    => $stateId > 0 ? $this->districts->getDistrictsByState($stateId) : $this->districts->getStates(),
        ]);
    }

    /**
     * GET /api/locations?q=pond — places for the location picker: districts,
     * cities / towns, alternate names, and a state's districts when a state is typed.
     * Every result carries the district it selects.
     */
    public function locations(Request $request): Response
    {
        $q = mb_strtolower(trim((string) $request->query('q', '')));
        if (mb_strlen($q) < 2) {
            return Response::json(['success' => true, 'data' => []]);
        }
        return Response::json(['success' => true, 'data' => $this->districts->searchPlaces($q)]);
    }

    public function categories(Request $request): Response
    {
        $rows = $this->categories->topLevel();
        return Response::json(['success' => true, 'data' => $rows]);
    }

    /** POST /api/set-city — legacy guest-session setter, kept for backward compatibility. */
    public function setCity(Request $request): Response
    {
        $cityId = (int) $request->post('city_id', 0);
        if ($cityId > 0) {
            $_SESSION['active_city_id'] = $cityId;
        }
        return Response::json(['success' => true, 'ok' => true]);
    }

    /**
     * POST /api/set-district — guest (and logged-in, for the current tab)
     * district selection. Writes the one session key every controller reads
     * (HomeController, EventController, DashboardController, ...).
     */
    public function setDistrict(Request $request): Response
    {
        $districtId = (int) $request->post('district_id', 0);

        // 0 = All India: forget the chosen district (and the saved preference)
        if ($districtId === 0) {
            $_SESSION['district_id'] = 0;
            unset($_SESSION['district_slug'], $_SESSION['district_name']);
            if ($request->isLoggedIn()) {
                $this->users->clearDistrictPreference($request->userId());
            }
            return Response::json(['success' => true, 'data' => null]);
        }

        $district   = $this->districts->findById($districtId);

        if (!$district) {
            return Response::json(['success' => false, 'message' => 'Unknown or inactive district.'], 422);
        }

        $_SESSION['district_id']   = $district['id'];
        $_SESSION['district_slug'] = $district['slug'];
        $_SESSION['district_name'] = $district['name'];

        // If the caller is logged in, persist it as their preference too —
        // so the choice survives the session, not just this browser tab.
        if ($request->isLoggedIn()) {
            $this->users->setDistrictPreference($request->userId(), $district['id']);
        }

        return Response::json(['success' => true, 'data' => $district]);
    }

    /**
     * POST /api/user/preferences/district — explicit logged-in preference
     * update (used by the dashboard selector). Requires auth; validates the
     * district exists and is active before saving.
     */
    public function updateDistrictPreference(Request $request): Response
    {
        if (!$request->isLoggedIn()) {
            return Response::json(['success' => false, 'message' => 'Please sign in to save a district preference.'], 401);
        }

        $districtId = (int) $request->post('district_id', 0);
        $district   = $this->districts->findById($districtId);

        if (!$district) {
            return Response::json(['success' => false, 'message' => 'That district is not available.', 'errors' => ['district_id' => 'Invalid or inactive district.']], 422);
        }

        $this->users->setDistrictPreference($request->userId(), $district['id']);

        $_SESSION['district_id']   = $district['id'];
        $_SESSION['district_slug'] = $district['slug'];
        $_SESSION['district_name'] = $district['name'];

        return Response::json(['success' => true, 'data' => $district]);
    }
}
