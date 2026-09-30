<?php

declare(strict_types=1);

namespace NEvents\Controllers\Public;

use NEvents\Core\Request;
use NEvents\Core\Response;
use NEvents\Core\View;
use NEvents\Repositories\EventRepository;
use NEvents\Repositories\CategoryRepository;
use NEvents\Repositories\UserRepository;
use NEvents\Services\Location\DistrictService;

class HomeController
{
    public function __construct(
        private EventRepository    $events,
        private CategoryRepository $categories,
        private DistrictService    $districts,
        private UserRepository     $users,
        private View               $view
    ) {}

    public function index(Request $request): Response
    {
        // The visitor's district (signed-in preference, then this visit's choice), else All India
        $district   = $this->districts->activeDistrict($request->isLoggedIn() ? $request->userId() : null);
        $districtId = $district['id'] ?? null;
        $place      = $district['name'] ?? 'India';

        $topCategories    = $this->categories->getFeatured(8);
        $featuredEvents   = $this->events->getFeatured($districtId, 8);   // rails show 8 on phones, 4 on desktop
        $todayEvents      = $this->events->getUpcomingToday($districtId);
        $weekendEvents    = $this->events->getThisWeekend($districtId);

        // Category-specific streams for homepage
        $techCategoryId     = 1;
        $startupCategoryId  = 3;

        $techEvents = $this->events->findPublished([
            'district_id' => $districtId,
            'category_id' => $techCategoryId,
        ], 1, 4);

        $startupEvents = $this->events->findPublished([
            'district_id' => $districtId,
            'category_id' => $startupCategoryId,
        ], 1, 4);

        $freeEvents = $this->events->findPublished([
            'district_id' => $districtId,
            'free'        => true,
        ], 1, 8);

        $onlineEvents = $this->events->findPublished([
            'format' => 'online',
        ], 1, 8);

        // "For you": the signed-in user's interest categories (their district first,
        // then anywhere in India if nothing matches locally)
        $forYou = [];
        if ($request->isLoggedIn()) {
            $interestIds = array_map('intval', array_column($this->users->getUserInterests($request->userId()), 'id'));
            if ($interestIds) {
                $forYou = ($districtId ? $this->events->findPublished(['category_ids' => $interestIds, 'district_id' => $districtId, 'sort' => 'soonest'], 1, 8) : [])
                       ?: $this->events->findPublished(['category_ids' => $interestIds, 'sort' => 'soonest'], 1, 8);
            }
        }

        // the app has its own home screen (no website hero / marketing sections)
        return $this->view->makeResponse(\NEvents\Helpers\AppMode::active() ? 'home.app' : 'home.index', [
            'title'          => 'N Events — Stop searching for events. Let the right events find you.',
            'meta_desc'      => 'Discover the best events in ' . $place . ' — tech meetups, startup events, workshops, cycling, networking and more. Free & personalized.',
            'district_id'    => $districtId,
            'district_slug'  => $district['slug'] ?? null,
            'district_name'  => $district['name'] ?? null,
            'state_name'     => $district['state_name'] ?? null,
            'district'       => $district,
            'place_name'     => $place,
            // Kept for any remaining view code expecting the older names.
            'city_id'        => $districtId,
            'city_slug'      => $district['slug'] ?? null,
            'city_name'      => $place,
            'categories'     => $topCategories,
            'featured_events'=> $featuredEvents,
            'today_events'   => $todayEvents,
            'weekend_events' => $weekendEvents,
            'tech_events'    => $techEvents,
            'startup_events' => $startupEvents,
            'free_events'    => $freeEvents,
            'online_events'  => $onlineEvents,
            'for_you'        => $forYou,
            'total_events'   => $this->events->countPublished(['district_id' => $districtId]),
            'total_cities'   => $this->events->countCitiesWithEvents(),
        ]);
    }
}
