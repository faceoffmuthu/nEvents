<?php

declare(strict_types=1);

namespace NEvents\Controllers\Admin;

use NEvents\Core\Request;
use NEvents\Core\Response;
use NEvents\Core\View;
use NEvents\Core\Database\Connection;
use NEvents\Services\Ingestion\IngestionService;

class AdminSourceController
{
    public function __construct(
        private Connection      $db,
        private IngestionService $ingestion,
    ) {}

    public function index(Request $request): Response
    {
        $sources = $this->db->select(
            "SELECT s.*, COUNT(r.id) AS run_count,
                    MAX(r.started_at) AS last_run
               FROM sources s
               LEFT JOIN source_runs r ON r.source_id = s.id
              GROUP BY s.id
              ORDER BY s.name ASC"
        );

        foreach ($sources as &$s) {
            $s['meta'] = $this->metadata($s);
        }
        unset($s);

        return View::make('admin/sources/index', [
            'title'   => 'Event Sources',
            'sources' => $sources,
        ]);
    }

    public function show(Request $request): Response
    {
        $id   = (int) $request->param('id', 0);
        $src  = $this->db->selectOne("SELECT * FROM sources WHERE id = :id", [':id' => $id]);

        if (!$src) {
            return Response::redirect('/admin/sources');
        }

        $runs = $this->db->select(
            "SELECT * FROM source_runs WHERE source_id = :id ORDER BY started_at DESC LIMIT 20",
            [':id' => $id]
        );

        return View::make('admin/sources/show', [
            'title'  => 'Source: ' . $src['name'],
            'source' => $src,
            'runs'   => $runs,
        ]);
    }

    public function toggle(Request $request): Response { return $this->toggleStatus($request); }

    public function runNow(Request $request): Response
    {
        $id  = (int) $request->param('id', 0);
        $src = $this->db->selectOne("SELECT * FROM sources WHERE id = :id", [':id' => $id]);

        if (!$src) {
            $_SESSION['flash_error'] = 'Source not found.';
            return Response::redirect('/admin/sources');
        }
        if (!$src['enabled']) {
            $_SESSION['flash_error'] = "{$src['name']} is disabled — enable it first.";
            return Response::redirect("/admin/sources/{$id}");
        }
        if (empty($src['adapter_class'])) {
            $_SESSION['flash_error'] = "{$src['name']} has no adapter_class configured.";
            return Response::redirect("/admin/sources/{$id}");
        }

        try {
            $fetchStats  = $this->ingestion->runSource($id);
            $processStats = $this->ingestion->processPendingRecords($id, 100);
            $_SESSION['flash_success'] = sprintf(
                '%s: discovered %d, fetched %d, %d new candidate(s), %d seen again, %d skipped by robots.txt. Processed: %d auto-published, %d held for review, %d existing events updated, %d matched with no changes, %d rejected, %d cancellations applied.',
                $src['name'],
                $fetchStats['discovered'], $fetchStats['fetched'], $fetchStats['inserted'], $fetchStats['seen_again'], $fetchStats['robots_skipped'] ?? 0,
                $processStats['published'], $processStats['review'], $processStats['updated'], $processStats['duplicate'], $processStats['rejected'], $processStats['cancelled']
            );
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = "{$src['name']} run failed: " . $e->getMessage();
        }

        return Response::redirect("/admin/sources/{$id}");
    }

    public function toggleStatus(Request $request): Response
    {
        $id  = (int) $request->param('id', 0);
        $src = $this->db->selectOne("SELECT * FROM sources WHERE id = :id", [':id' => $id]);

        if ($src) {
            $newStatus = $src['enabled'] ? 0 : 1;
            $meta = $this->metadata($src);
            // An adapter class existing is not enough: a connector without its
            // credentials/config can't be switched on.
            if ($newStatus && $src['adapter_class'] && !$meta['configured']) {
                $_SESSION['flash_error'] = "{$src['name']} can't be enabled yet — missing: " . (implode(', ', $meta['credentials']) ?: 'config_json.url');
                return Response::redirect(\NEvents\Core\View::url('admin/sources'));
            }
            $this->db->update(
                "UPDATE sources SET enabled = :enabled, health_status = IF(:en2 = 1, 'unknown', 'disabled') WHERE id = :id",
                [':enabled' => $newStatus, ':en2' => $newStatus, ':id' => $id]
            );
            $_SESSION['flash_success'] = $newStatus ? 'Source enabled.' : 'Source disabled.';
        }

        return Response::redirect(\NEvents\Core\View::url('admin/sources'));
    }

    private function metadata(array $src): array
    {
        $adapter = $src['adapter_class'] ? $this->ingestion->adapterFor($src['adapter_class']) : null;
        if (!$adapter) {
            return ['platform' => $src['platform'] ?? 'internal', 'acquisition_method' => $src['acquisition_method'],
                    'credentials' => [], 'configured' => true, 'limitations' => 'Events created on the platform.'];
        }
        return $adapter->getSourceMetadata(json_decode($src['config_json'] ?? '{}', true) ?? []);
    }
}
