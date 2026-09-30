<?php

declare(strict_types=1);

namespace NEvents\Controllers\Dashboard;

use NEvents\Core\Request;
use NEvents\Core\Response;
use NEvents\Core\View;
use NEvents\Repositories\CategoryRepository;
use NEvents\Services\Events\UserEventService;
use NEvents\Services\Location\DistrictService;

/**
 * "Post an Event" + "My Events". Every route here sits behind AuthMiddleware;
 * ownership is re-checked server-side on every edit/cancel/delete via
 * UserEventService::findManageable() — never trusted from form fields.
 */
class UserEventController
{
    public function __construct(
        private UserEventService   $service,
        private CategoryRepository $categories,
        private DistrictService    $districts,
    ) {}

    public function createForm(Request $request): Response
    {
        return $this->form($request, 'create', [
            'format' => 'offline', 'timezone' => 'Asia/Kolkata', 'currency' => 'INR',
            'pricing_type' => 'free', 'registration_required' => '1', 'primary_language' => 'en',
        ]);
    }

    public function store(Request $request): Response
    {
        $result = $this->service->create($request->userId(), $this->isModerator(), $_POST, $request->file('image'));

        if (!$result['ok']) {
            return $this->form($request, 'create', $_POST, $result['errors'] ?? [], $result['duplicate'] ?? null, 422);
        }

        return Response::redirect(View::url('my-events/' . $result['event_id'] . '/submitted'));
    }

    public function submitted(Request $request): Response
    {
        $event = $this->service->findManageable((int) $request->param('id'), $request->userId(), $this->isModerator());
        if (!$event) {
            return View::make('errors/404', ['title' => 'Not Found'], 404);
        }
        return View::make('user-events/submitted', [
            'title'  => $event['status'] === 'published' ? 'Your event has been published' : 'Your event was received',
            'event'  => $event,
            'reason' => $this->service->reasonForHumans($event['moderation_reason']),
        ]);
    }

    public function index(Request $request): Response
    {
        $groups = $this->service->myEvents($request->userId());
        $tab    = (string) $request->query('tab', '');
        if (!isset($groups[$tab])) {
            $tab = 'all';
        }
        return View::make('user-events/index', [
            'title'  => 'My Events',
            'groups' => $groups,
            'tab'    => $tab,
        ]);
    }

    public function editForm(Request $request): Response
    {
        $event = $this->service->findManageable((int) $request->param('id'), $request->userId(), $this->isModerator());
        if (!$event) {
            return View::make('errors/404', ['title' => 'Not Found'], 404);
        }
        return $this->form($request, 'edit', $this->service->formValues($event), [], null, 200, $event);
    }

    public function update(Request $request): Response
    {
        $id     = (int) $request->param('id');
        $result = $this->service->update($id, $request->userId(), $this->isModerator(), $_POST, $request->file('image'));

        if (!$result['ok']) {
            if (!empty($result['forbidden'])) {
                return View::make('errors/404', ['title' => 'Not Found'], 404);
            }
            $event = $this->service->findManageable($id, $request->userId(), $this->isModerator());
            $old   = $_POST + ['featured_image_url' => $event['featured_image_url'] ?? null];
            return $this->form($request, 'edit', $old, $result['errors'] ?? [], $result['duplicate'] ?? null, 422, $event ?: null);
        }

        $_SESSION['flash_success'] = $result['status'] === 'published'
            ? 'Event updated.'
            : 'Event updated — it is being held for a quick review. ' . $this->service->reasonForHumans($result['review_reason']);
        return Response::redirect(View::url('my-events'));
    }

    public function cancel(Request $request): Response
    {
        $result = $this->service->cancel((int) $request->param('id'), $request->userId(), $this->isModerator());
        if (!empty($result['forbidden'])) {
            return View::make('errors/404', ['title' => 'Not Found'], 404);
        }
        $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['ok']
            ? 'Event cancelled. People who saved it will be notified by email.'
            : $result['error'];
        return Response::redirect(View::url('my-events'));
    }

    public function delete(Request $request): Response
    {
        $result = $this->service->delete((int) $request->param('id'), $request->userId(), $this->isModerator());
        if (!empty($result['forbidden'])) {
            return View::make('errors/404', ['title' => 'Not Found'], 404);
        }
        $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['ok'] ? 'Event deleted.' : $result['error'];
        return Response::redirect(View::url('my-events'));
    }

    private function form(Request $request, string $mode, array $old, array $errors = [], ?array $duplicate = null,
                          int $status = 200, ?array $event = null): Response
    {
        return View::make('user-events/form', [
            'title'      => $mode === 'create' ? 'Post an Event — N Events' : 'Edit Event — N Events',
            'mode'       => $mode,
            'event'      => $event,
            'old'        => $old,
            'errors'     => $errors,
            'duplicate'  => $duplicate,
            'categories' => $this->categories->withChildren(),
            // the district shown in the location picker (a new event starts at the poster's own district)
            'district'   => ($did = (int) ($old['district_id'] ?? 0) ?: ($mode === 'create' ? (int) ($this->districts->activeDistrict($request->userId())['id'] ?? 0) : 0))
                                ? ($this->districts->findById($did) ?: null) : null,
            'organizer'  => $this->service->organizerForUser($event ? (int) ($event['created_by_user_id'] ?? $request->userId()) : $request->userId()),
            'languages'  => UserEventService::LANGUAGES,
            'currencies' => UserEventService::CURRENCIES,
        ], $status);
    }

    private function isModerator(): bool
    {
        return (bool) array_intersect($_SESSION['user_roles'] ?? [], ['admin', 'super_admin', 'moderator']);
    }
}
