<?php

declare(strict_types=1);

namespace NEvents\Controllers\Public;

use NEvents\Core\Request;
use NEvents\Core\Response;
use NEvents\Core\View;
use NEvents\Repositories\EventRepository;
use NEvents\Repositories\CategoryRepository;
use NEvents\Repositories\CityRepository;

class SearchController
{
    public function __construct(
        private EventRepository    $events,
        private CategoryRepository $categories,
        private CityRepository     $cities,
    ) {}

    public function index(Request $request): Response
    {
        $q      = trim($request->query('q', ''));
        $page   = max(1, (int) $request->query('page', 1));
        $perPage = 20;

        $filters = ['search' => $q ?: null];
        if ($request->query('district')) {
            $filters['district_id'] = (int) $request->query('district');
        }
        $events  = $q ? $this->events->findPublished($filters, $page, $perPage) : [];
        $total   = $q ? $this->events->countPublished($filters) : 0;

        return View::make('events/search', [
            'title'      => $q ? "Search: {$q}" : 'Search Events',
            'query'      => $q,
            'events'     => $events,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $perPage,
            'categories' => $this->categories->topLevel(),
            'cities'     => $this->cities->featured(),
        ]);
    }
}
