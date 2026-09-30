<?php

declare(strict_types=1);

namespace NEvents\Controllers\Organizer;

use NEvents\Core\Request;
use NEvents\Core\Response;
use NEvents\Core\View;
use NEvents\Core\Database\Connection;

class OrganizerPortalController
{
    public function __construct(private Connection $db) {}

    private function getOrganizerForUser(int $userId): array|false
    {
        return $this->db->selectOne(
            "SELECT o.* FROM organizers o
               JOIN organizer_users ou ON ou.organizer_id = o.id
              WHERE ou.user_id = :uid
              LIMIT 1",
            [':uid' => $userId]
        );
    }

    public function dashboard(Request $request): Response
    {
        $userId    = $request->userId();
        $organizer = $this->getOrganizerForUser($userId);

        if (!$organizer) {
            return Response::redirect('/organizer-portal/setup');
        }

        $stats = [
            'total_events'     => (int) ($this->db->selectOne(
                "SELECT COUNT(*) AS n FROM events WHERE primary_organizer_id = :oid",
                [':oid' => $organizer['id']]
            )['n'] ?? 0),
            'published_events' => (int) ($this->db->selectOne(
                "SELECT COUNT(*) AS n FROM events WHERE primary_organizer_id = :oid AND status='published'",
                [':oid' => $organizer['id']]
            )['n'] ?? 0),
            'total_views'      => (int) ($this->db->selectOne(
                "SELECT COALESCE(SUM(view_count),0) AS n FROM events WHERE primary_organizer_id = :oid",
                [':oid' => $organizer['id']]
            )['n'] ?? 0),
        ];

        $events = $this->db->select(
            "SELECT e.id, e.title, e.slug, e.status, e.view_count, e.click_count, e.created_at
               FROM events e
              WHERE e.primary_organizer_id = :oid
              ORDER BY e.created_at DESC LIMIT 10",
            [':oid' => $organizer['id']]
        );

        return View::make('organizer-portal/dashboard', [
            'title'     => 'Organizer Dashboard',
            'organizer' => $organizer,
            'stats'     => $stats,
            'events'    => $events,
        ]);
    }

    public function setup(Request $request): Response
    {
        return View::make('organizer-portal/setup', [
            'title' => 'Set Up Your Organizer Profile',
        ]);
    }

    /** Event creation is one flow for everyone — organizers pick "post as my organizer profile" there. */
    public function createEvent(Request $request): Response
    {
        return Response::redirect(View::url('events/create'));
    }

    public function myEvents(Request $request): Response
    {
        $userId    = $request->userId();
        $organizer = $this->getOrganizerForUser($userId);

        if (!$organizer) {
            return Response::redirect('/organizer-portal/setup');
        }

        $events = $this->db->select(
            "SELECT e.id, e.title, e.slug, e.status, e.view_count, e.click_count,
                    e.pricing_type, e.created_at
               FROM events e
              WHERE e.primary_organizer_id = :oid
              ORDER BY e.created_at DESC",
            [':oid' => $organizer['id']]
        );

        return View::make('organizer-portal/my-events', [
            'title'     => 'My Events',
            'organizer' => $organizer,
            'events'    => $events,
        ]);
    }

    public function setupPost(Request $request): Response  { return Response::redirect('/organizer-portal/setup'); }
    public function submitForm(Request $request): Response  { return $this->createEvent($request); }
    public function submitPost(Request $request): Response  { return $this->createEvent($request); }
    public function editForm(Request $request): Response    { return Response::redirect(View::url('my-events/' . (int) $request->param('id') . '/edit')); }
    public function editPost(Request $request): Response    { return $this->editForm($request); }
    public function analytics(Request $request): Response   { return $this->dashboard($request); }
    public function verification(Request $request): Response { return $this->dashboard($request); }
    public function requestVerification(Request $request): Response { return Response::redirect('/organizer-portal/verification'); }
    public function settings(Request $request): Response    { return $this->profile($request); }
    public function settingsPost(Request $request): Response { return Response::redirect('/organizer-portal/settings'); }

    public function profile(Request $request): Response
    {
        $userId    = $request->userId();
        $organizer = $this->getOrganizerForUser($userId);

        if (!$organizer) {
            return Response::redirect('/organizer-portal/setup');
        }

        return View::make('organizer-portal/profile', [
            'title'     => 'Organizer Profile',
            'organizer' => $organizer,
        ]);
    }
}
