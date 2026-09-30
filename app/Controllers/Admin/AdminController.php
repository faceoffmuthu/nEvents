<?php

declare(strict_types=1);

namespace NEvents\Controllers\Admin;

use NEvents\Core\Request;
use NEvents\Core\Response;
use NEvents\Core\View;
use NEvents\Core\Database\Connection;
use NEvents\Repositories\DistrictRepository;
use NEvents\Services\Moderation\ModerationService;

class AdminController
{
    public function __construct(
        private Connection         $db,
        private DistrictRepository $districts,
        private ModerationService  $moderation,
    ) {}

    public function dashboard(Request $request): Response
    {
        $stats = [
            'total_events'     => (int) ($this->db->selectOne("SELECT COUNT(*) AS n FROM events")['n'] ?? 0),
            'published_events' => (int) ($this->db->selectOne("SELECT COUNT(*) AS n FROM events WHERE status='published'")['n'] ?? 0),
            'total_users'      => (int) ($this->db->selectOne("SELECT COUNT(*) AS n FROM users")['n'] ?? 0),
            'total_organizers' => (int) ($this->db->selectOne("SELECT COUNT(*) AS n FROM organizers")['n'] ?? 0),
            'pending_reports'  => (int) ($this->db->selectOne("SELECT COUNT(*) AS n FROM reports WHERE status='open'")['n'] ?? 0),
            'pending_jobs'     => (int) ($this->db->selectOne("SELECT COUNT(*) AS n FROM jobs WHERE completed_at IS NULL AND failed_at IS NULL")['n'] ?? 0),
        ];

        $recentEvents = $this->db->select(
            "SELECT e.id, e.title, e.status, e.created_at, o.name AS organizer_name
               FROM events e
               LEFT JOIN organizers o ON o.id = e.primary_organizer_id
              ORDER BY e.created_at DESC LIMIT 10"
        );

        return View::make('admin/dashboard', [
            'title'        => 'Admin Dashboard',
            'stats'        => $stats,
            'recentEvents' => $recentEvents,
        ]);
    }

    private function stub(string $title): Response
    {
        return View::make('admin/dashboard', ['title' => $title, 'stats' => [], 'recentEvents' => []]);
    }

    public function organizers(Request $request): Response  { return $this->stub('Organizers'); }
    public function verifyOrganizer(Request $request): Response { return Response::redirect('/admin/organizers'); }
    public function categories(Request $request): Response  { return $this->stub('Categories'); }

    /**
     * India's districts (optionally one state's), each with a live published-event
     * count and an enable/disable toggle. There is exactly one districts table —
     * this page reads/writes it directly, same as every other district selector.
     */
    public function districts(Request $request): Response
    {
        $stateId = (int) $request->query('state', 0);
        $rows = $this->db->select(
            "SELECT d.*, s.name AS state_name,
                    (SELECT COUNT(*) FROM events e JOIN cities c ON c.id = e.city_id
                      WHERE c.district_id = d.id AND e.status = 'published') AS event_count
               FROM districts d JOIN states s ON s.id = d.state_id
              WHERE (:s = 0 OR d.state_id = :s2)
              ORDER BY s.name ASC, d.name ASC",
            [':s' => $stateId, ':s2' => $stateId]
        );

        return View::make('admin/districts/index', [
            'title'     => 'Districts',
            'districts' => $rows,
            'states'    => $this->districts->states(),
            'state_id'  => $stateId,
            'total'     => (int) ($this->db->selectOne("SELECT COUNT(*) AS n FROM districts")['n'] ?? 0),
        ]);
    }

    public function districtToggle(Request $request): Response
    {
        $id = (int) $request->param('id', 0);
        $d  = $this->districts->findById($id);

        if ($d) {
            $this->districts->setActive($id, !$d['is_active']);
            $_SESSION['flash_success'] = $d['is_active']
                ? "{$d['name']} disabled — it will no longer appear in district selectors."
                : "{$d['name']} enabled.";
        }

        return Response::redirect('/admin/districts' . ($d ? '?state=' . (int) $d['state_id'] : ''));
    }

    public function reports(Request $request): Response
    {
        $status = (string) $request->query('status', 'open');
        $params = [];
        $where  = '1=1';
        if (in_array($status, ['open', 'reviewing', 'resolved', 'dismissed'], true)) {
            $where = 'r.status = :status';
            $params[':status'] = $status;
        }
        $reports = $this->db->select(
            "SELECT r.*, e.title AS event_title, e.slug AS event_slug, e.status AS event_status, e.moderation_status,
                    u.name AS reporter_name, u.email AS reporter_email,
                    (SELECT COUNT(*) FROM reports r2 WHERE r2.event_id = r.event_id AND r2.status IN ('open','reviewing')) AS event_open_reports
               FROM reports r
               JOIN events e ON e.id = r.event_id
               LEFT JOIN users u ON u.id = r.user_id
              WHERE {$where}
              ORDER BY r.created_at DESC LIMIT 200",
            $params
        );
        return View::make('admin/reports/index', ['title' => 'Reported Events', 'reports' => $reports, 'status' => $status]);
    }

    public function resolveReport(Request $request): Response
    {
        $result = $this->moderation->resolveReport(
            (int) $request->param('id', 0),
            (int) $request->userId(),
            (string) $request->post('status', ''),
            (string) $request->post('resolution', '')
        );
        $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['ok'] ? 'Report updated.' : $result['error'];
        return Response::redirect(View::url('admin/reports'));
    }
    public function analytics(Request $request): Response   { return $this->stub('Analytics'); }
    public function auditLog(Request $request): Response    { return $this->stub('Audit Log'); }
    public function system(Request $request): Response      { return $this->stub('System Settings'); }
    public function systemPost(Request $request): Response  { return Response::redirect('/admin/system'); }
}
