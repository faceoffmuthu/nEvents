<?php

declare(strict_types=1);

namespace NEvents\Controllers\Admin;

use NEvents\Core\Request;
use NEvents\Core\Response;
use NEvents\Core\View;
use NEvents\Core\Database\Connection;
use NEvents\Repositories\DistrictRepository;
use NEvents\Services\Moderation\ModerationService;

class AdminEventController
{
    /** Views of the event table (All / User-submitted / Discovered / Social / Flagged / Reported). */
    public const VIEWS = [
        ''          => 'All Events',
        'user'      => 'User-Submitted',
        'external'  => 'Externally Discovered',
        'social'    => 'Socially Discovered',
        'flagged'   => 'Flagged / Needs Review',
        'reported'  => 'Reported',
    ];

    /** Source filter: data_origin values + source platforms. */
    public const SOURCE_FILTERS = [
        'user_submitted'      => 'User submitted',
        'web_discovered'      => 'Web discovered',
        'instagram'           => 'Instagram',
        'facebook'            => 'Facebook',
        'x'                   => 'X / Twitter',
        'organizer_website'   => 'Organizer website',
        'event_platform'      => 'Event platform',
        'partner_feed'        => 'Partner feed',
        'organizer_submitted' => 'Organizer submitted',
        'admin_created'       => 'Admin created',
        'demo'                => 'Demo / seed',
    ];

    public function __construct(
        private Connection         $db,
        private DistrictRepository $districts,
        private ModerationService  $moderation,
    ) {}

    public function index(Request $request): Response
    {
        $view       = (string) $request->query('view', '');
        $status     = (string) $request->query('status', '');
        $districtId = (int) $request->query('district_id', 0);
        $source     = (string) $request->query('source', (string) $request->query('data_origin', ''));
        $q          = trim((string) $request->query('q', ''));
        $page       = max(1, (int) $request->query('page', 1));
        $limit      = 25;
        $offset     = ($page - 1) * $limit;

        $where  = ['1=1'];
        $params = [];
        $socialPlatforms = "('instagram','facebook','x')";

        match ($view) {
            'user'     => $where[] = "e.data_origin = 'user_submitted'",
            'external' => $where[] = "e.data_origin IN ('discovered','partner_feed')",
            'social'   => $where[] = "EXISTS (SELECT 1 FROM event_sources es JOIN sources s ON s.id = es.source_id WHERE es.event_id = e.id AND s.platform IN {$socialPlatforms})",
            'flagged'  => $where[] = "e.moderation_status IN ('flagged','needs_review')",
            'reported' => $where[] = "EXISTS (SELECT 1 FROM reports r WHERE r.event_id = e.id AND r.status IN ('open','reviewing'))",
            default    => null,
        };
        if ($status !== '') {
            $where[] = 'e.status = :status';
            $params[':status'] = $status;
        }
        if ($districtId) {
            $where[] = 'c.district_id = :district_id';
            $params[':district_id'] = $districtId;
        }
        if ($source !== '') {
            if (in_array($source, ['instagram', 'facebook', 'x'], true)) {
                $where[] = "EXISTS (SELECT 1 FROM event_sources es JOIN sources s ON s.id = es.source_id WHERE es.event_id = e.id AND s.platform = :platform)";
                $params[':platform'] = $source;
            } elseif (in_array($source, ['organizer_website', 'event_platform'], true)) {
                $where[] = "EXISTS (SELECT 1 FROM event_sources es JOIN sources s ON s.id = es.source_id WHERE es.event_id = e.id AND s.source_type = :stype)";
                $params[':stype'] = $source;
            } elseif ($source === 'web_discovered') {
                $where[] = "e.data_origin = 'discovered' AND NOT EXISTS (SELECT 1 FROM event_sources es JOIN sources s ON s.id = es.source_id WHERE es.event_id = e.id AND s.platform IN {$socialPlatforms})";
            } elseif ($source === 'demo') {
                $where[] = "e.data_origin IN ('demo','seed')";
            } elseif (isset(self::SOURCE_FILTERS[$source])) {
                $where[] = 'e.data_origin = :origin';
                $params[':origin'] = $source;
            }
        }
        if ($q !== '') {
            $where[] = '(e.title LIKE :q OR u.email LIKE :q2)';
            $params[':q']  = '%' . $q . '%';
            $params[':q2'] = '%' . $q . '%';
        }
        $whereSql = implode(' AND ', $where);

        $events = $this->db->select(
            "SELECT e.id, e.title, e.slug, e.status, e.moderation_status, e.moderation_reason, e.format, e.pricing_type,
                    e.data_origin, e.created_at, e.created_by_user_id,
                    COALESCE(o.name, e.organizer_display_name) AS organizer_name, c.name AS city_name, d.name AS district_name,
                    u.name AS submitter_name, u.email AS submitter_email, u.can_post_events,
                    catp.name AS category_name,
                    (SELECT MIN(eo.start_at_utc) FROM event_occurrences eo WHERE eo.event_id = e.id) AS next_start,
                    (SELECT COUNT(*) FROM reports r WHERE r.event_id = e.id AND r.status IN ('open','reviewing')) AS open_reports,
                    (SELECT GROUP_CONCAT(DISTINCT s.platform) FROM event_sources es JOIN sources s ON s.id = es.source_id WHERE es.event_id = e.id) AS platforms
               FROM events e
               LEFT JOIN organizers o ON o.id = e.primary_organizer_id
               LEFT JOIN cities c ON c.id = e.city_id
               LEFT JOIN districts d ON d.id = c.district_id
               LEFT JOIN users u ON u.id = e.created_by_user_id
               LEFT JOIN event_categories ecp ON ecp.event_id = e.id AND ecp.is_primary = 1
               LEFT JOIN categories catp ON catp.id = ecp.category_id
              WHERE {$whereSql}
              ORDER BY e.created_at DESC LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        $total = (int) ($this->db->selectOne(
            "SELECT COUNT(*) AS n FROM events e LEFT JOIN cities c ON c.id = e.city_id LEFT JOIN users u ON u.id = e.created_by_user_id WHERE {$whereSql}",
            $params
        )['n'] ?? 0);

        return View::make('admin/events/index', [
            'title'       => 'Manage Events',
            'events'      => $events,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $limit,
            'view'        => $view,
            'status'      => $status,
            'district_id' => $districtId,
            'source'      => $source,
            'q'           => $q,
            'districts'   => $this->districts->withEvents(),
            'views'       => self::VIEWS,
            'sources'     => self::SOURCE_FILTERS,
        ]);
    }

    public function show(Request $request): Response
    {
        $id    = (int) $request->param('id', 0);
        $event = $this->db->selectOne(
            "SELECT e.*, u.name AS submitter_name, u.email AS submitter_email, u.can_post_events, d.name AS district_name,
                    (SELECT MIN(eo.start_at_utc) FROM event_occurrences eo WHERE eo.event_id = e.id) AS next_start
               FROM events e LEFT JOIN users u ON u.id = e.created_by_user_id
               LEFT JOIN cities c ON c.id = e.city_id LEFT JOIN districts d ON d.id = c.district_id
              WHERE e.id = :id",
            [':id' => $id]
        );
        if (!$event) {
            return Response::redirect(View::url('admin/events'));
        }

        return View::make('admin/events/show', [
            'title'   => 'Event: ' . $event['title'],
            'event'   => $event,
            'sources' => $this->db->select(
                "SELECT es.*, s.name AS source_name, s.platform FROM event_sources es JOIN sources s ON s.id = es.source_id WHERE es.event_id = :id ORDER BY es.is_primary DESC, es.id",
                [':id' => $id]
            ),
            'reports' => $this->db->select(
                "SELECT r.*, u.name AS reporter_name FROM reports r LEFT JOIN users u ON u.id = r.user_id WHERE r.event_id = :id ORDER BY r.created_at DESC",
                [':id' => $id]
            ),
            'conflicts' => $this->db->select(
                "SELECT ec.*, sa.name AS source_a, sb.name AS source_b FROM event_conflicts ec
                   LEFT JOIN sources sa ON sa.id = ec.source_a_id LEFT JOIN sources sb ON sb.id = ec.source_b_id
                  WHERE ec.event_id = :id ORDER BY ec.status = 'open' DESC, ec.created_at DESC LIMIT 50",
                [':id' => $id]
            ),
            'history' => $this->db->select(
                "SELECT a.*, u.name AS actor_name FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_id
                  WHERE a.entity_type = 'event' AND a.entity_id = :id ORDER BY a.created_at DESC LIMIT 25",
                [':id' => $id]
            ),
        ]);
    }

    /** publish | unpublish | flag | unflag | reject | suspend | cancel */
    public function moderate(Request $request): Response
    {
        $id     = (int) $request->param('id', 0);
        $action = (string) $request->post('action', '');
        $result = $this->moderation->apply($action, $id, (int) $request->userId(), (string) $request->post('reason', ''));
        $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['ok'] ? "Event {$action} applied." : $result['error'];
        return Response::redirect(View::url('admin/events/' . $id));
    }

    public function publish(Request $request): Response { return $this->legacyAction($request, 'publish'); }
    public function reject(Request $request): Response  { return $this->legacyAction($request, 'reject'); }
    public function cancel(Request $request): Response  { return $this->legacyAction($request, 'cancel'); }

    public function updateStatus(Request $request): Response
    {
        $map = ['published' => 'publish', 'rejected' => 'reject', 'cancelled' => 'cancel', 'draft' => 'unpublish', 'archived' => 'suspend'];
        return $this->legacyAction($request, $map[(string) $request->post('status', '')] ?? '');
    }

    /** Suspend or reinstate a submitter's ability to post events (account stays active). */
    public function submitterPosting(Request $request): Response
    {
        $userId  = (int) $request->param('id', 0);
        $allowed = $request->post('allow', '0') === '1';
        $result  = $this->moderation->setSubmitterPosting($userId, $allowed, (int) $request->userId(), (string) $request->post('reason', ''));
        $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['ok']
            ? ($allowed ? 'Submitter can post events again.' : 'Submitter suspended from posting events.')
            : $result['error'];
        $back = (int) $request->post('event_id', 0);
        return Response::redirect(View::url($back ? 'admin/events/' . $back : 'admin/events?view=user'));
    }

    public function duplicates(Request $request): Response
    {
        $pairs = $this->db->select(
            "SELECT dc.*, a.title AS a_title, a.slug AS a_slug, a.status AS a_status, a.data_origin AS a_origin,
                    b.title AS b_title, b.slug AS b_slug, b.status AS b_status, b.data_origin AS b_origin
               FROM duplicate_candidates dc
               JOIN events a ON a.id = dc.event_a_id
               JOIN events b ON b.id = dc.event_b_id
              WHERE dc.status = 'pending'
              ORDER BY dc.score DESC, dc.created_at DESC
              LIMIT 100"
        );
        return View::make('admin/events/duplicates', ['title' => 'Duplicate Candidates', 'pairs' => $pairs]);
    }

    public function merge(Request $request): Response
    {
        $result = $this->moderation->mergeDuplicate((int) $request->param('id', 0), (int) $request->post('survivor_id', 0), (int) $request->userId());
        $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['ok'] ? 'Events merged.' : $result['error'];
        return Response::redirect(View::url('admin/events/duplicates'));
    }

    public function dismissDuplicate(Request $request): Response
    {
        $this->moderation->dismissDuplicate((int) $request->param('id', 0), (int) $request->userId());
        $_SESSION['flash_success'] = 'Marked as not a duplicate.';
        return Response::redirect(View::url('admin/events/duplicates'));
    }

    /** Close a source-conflict note (the admin edits the event itself if the new value is right). */
    public function resolveConflict(Request $request): Response
    {
        $id     = (int) $request->param('id', 0);
        $status = $request->post('status') === 'resolved' ? 'resolved' : 'dismissed';
        $row    = $this->db->selectOne("SELECT event_id FROM event_conflicts WHERE id = :id", [':id' => $id]);
        if ($row) {
            $this->db->update(
                "UPDATE event_conflicts SET status = :s, resolved_by = :u, resolved_at = NOW() WHERE id = :id",
                [':s' => $status, ':u' => $request->userId(), ':id' => $id]
            );
            $_SESSION['flash_success'] = 'Conflict ' . $status . '.';
        }
        return Response::redirect(View::url('admin/events/' . (int) ($row['event_id'] ?? 0)));
    }

    public function pending(Request $request): Response
    {
        $_GET['view'] = 'flagged';
        return $this->index($request);
    }

    // Admin-side creation reuses the same form/service as users (/events/create);
    // moderators may edit any event through /my-events/{id}/edit.
    public function createForm(Request $request): Response { return Response::redirect(View::url('events/create')); }
    public function createPost(Request $request): Response { return Response::redirect(View::url('events/create')); }
    public function editForm(Request $request): Response   { return Response::redirect(View::url('my-events/' . (int) $request->param('id') . '/edit')); }
    public function editPost(Request $request): Response   { return $this->editForm($request); }

    private function legacyAction(Request $request, string $action): Response
    {
        $id     = (int) $request->param('id', 0);
        $result = $this->moderation->apply($action, $id, (int) $request->userId(), (string) $request->post('reason', ''));
        $_SESSION[$result['ok'] ? 'flash_success' : 'flash_error'] = $result['ok'] ? "Event {$action} applied." : $result['error'];
        return Response::redirect(View::url('admin/events/' . $id));
    }
}
