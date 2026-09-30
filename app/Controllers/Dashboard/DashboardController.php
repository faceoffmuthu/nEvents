<?php

declare(strict_types=1);

namespace NEvents\Controllers\Dashboard;

use NEvents\Core\Request;
use NEvents\Core\Response;
use NEvents\Core\View;
use NEvents\Repositories\EventRepository;
use NEvents\Repositories\UserRepository;
use NEvents\Services\Location\DistrictService;

class DashboardController
{
    public function __construct(
        private EventRepository $events,
        private UserRepository  $users,
        private DistrictService $districts,
        private \NEvents\Repositories\CategoryRepository $categories,
        private \NEvents\Services\Auth\AuthService $auth,
    ) {}

    public function index(Request $request): Response
    {
        $userId    = $request->userId();
        $saved     = $this->events->getSavedEvents($userId);
        $prefs     = $this->users->getNotificationPreferences($userId);
        $interests = $this->users->getUserInterests($userId);
        $district  = $this->districts->activeDistrict($userId);   // null = All India

        $districtEvents = $this->events->getFeatured($district['id'] ?? null, 8);

        return View::make('dashboard/index', [
            'title'           => 'My Profile',
            'user'            => $this->users->findById($userId),
            'my_events_count' => $this->events->countByCreator($userId),
            'saved'           => $saved,
            'prefs'           => $prefs,
            'interests'       => $interests,
            'district_id'     => $district['id'] ?? null,
            'district_name'   => $district['name'] ?? null,
            'district'        => $district ? $this->districts->findById($district['id']) : null,
            'district_events' => $districtEvents,
        ]);
    }

    public function savedEvents(Request $request): Response
    {
        $userId = $request->userId();
        $events = $this->events->getSavedEvents($userId);

        return View::make('dashboard/saved-events', [
            'title'  => 'Saved Events',
            'events' => $events,
        ]);
    }

    public function preferences(Request $request): Response
    {
        $userId = $request->userId();

        // Email is the only notification channel (plus Google Calendar links on
        // event pages). WhatsApp numbers are collected at signup but never messaged.
        $row = $this->users->getNotificationPreferences($userId) ?: [];

        if ($request->isPost()) {
            $data       = $request->all();
            $digestTime = (string) ($data['digest_time'] ?? '08:00');
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $digestTime)) {
                $digestTime = '08:00';
            }

            $this->users->updateNotificationPreferences($userId, [
                'email_enabled'    => isset($data['email_enabled']) ? 1 : 0,
                'daily_digest'     => isset($data['daily_digest']) ? 1 : 0,
                'weekly_digest'    => isset($data['weekly_digest']) ? 1 : 0,
                'reminder_24h'     => isset($data['reminder_24h']) ? 1 : 0,
                'reminder_2h'      => isset($data['reminder_2h']) ? 1 : 0,
                'event_updates'    => isset($data['event_updates']) ? 1 : 0,
                'digest_time'      => $digestTime . ':00',
                'alert_high_match' => (int) ($row['alert_high_match'] ?? 1),
                'min_score'        => (int) ($row['min_score'] ?? 40),
            ]);
            $_SESSION['flash_success'] = 'Preferences saved.';
            return Response::redirect(View::url('account/preferences'));
        }

        return View::make('dashboard/preferences', [
            'title' => 'Notification Preferences',
            'prefs' => $row,
        ]);
    }

    public function account(Request $request): Response
    {
        $userId = $request->userId();
        $user   = $this->users->findById($userId);

        return View::make('dashboard/account', [
            'title' => 'My Account',
            'user'  => $user,
        ]);
    }

    public function updateAccount(Request $request): Response
    {
        $result = $this->auth->updateProfile($request->userId(), [
            'name'            => $request->post('name'),
            'whatsapp_number' => $request->post('whatsapp_number'),
        ]);
        if (!$result['success']) {
            return View::make('dashboard/account', [
                'title'  => 'My Account',
                'user'   => array_merge((array) $this->users->findById($request->userId()), ['name' => $request->post('name'), 'whatsapp_number' => $request->post('whatsapp_number')]),
                'errors' => $result['errors'],
            ], 422);
        }
        $_SESSION['flash_success'] = 'Profile updated.';
        return Response::redirect(View::url('account'));
    }

    public function updatePreferences(Request $request): Response
    {
        return $this->preferences($request);
    }

    public function viewHistory(Request $request): Response
    {
        return Response::redirect(View::url('dashboard'));   // not built yet: go to the profile instead of a half-empty page
    }

    public function followedOrganizers(Request $request): Response
    {
        return View::make('dashboard/index', ['title' => 'Followed Organizers', 'saved' => [], 'interests' => [], 'prefs' => []]);
    }

    public function notifications(Request $request): Response
    {
        return View::make('dashboard/preferences', ['title' => 'Notifications', 'prefs' => []]);
    }

    public function interests(Request $request): Response
    {
        return View::make('dashboard/interests', [
            'title'      => 'My Interests',
            'categories' => $this->categories->withChildren(),
            'selected'   => array_map('intval', array_column($this->users->getUserInterests($request->userId()), 'id')),
        ]);
    }

    public function updateInterests(Request $request): Response
    {
        $valid = array_map('intval', array_column($this->categories->all(), 'id'));
        $ids   = array_values(array_intersect(array_map('intval', (array) $request->post('categories', [])), $valid));
        $this->users->setInterests($request->userId(), array_slice($ids, 0, 30));
        $_SESSION['flash_success'] = $ids ? 'Interests saved. "For you" on the home page now follows them.' : 'Interests cleared.';
        return Response::redirect(View::url('dashboard'));
    }

    public function privacy(Request $request): Response
    {
        $appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
        return Response::redirect($appUrl . '/privacy');
    }

    public function deleteAccount(Request $request): Response
    {
        return Response::redirect('/account');
    }
}
