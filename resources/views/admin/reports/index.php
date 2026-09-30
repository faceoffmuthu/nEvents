<?php
use NEvents\Core\View;
use NEvents\Services\Moderation\ModerationService;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$td = 'padding:.7rem 1rem;font-size:.8rem;color:var(--ne-text-muted);vertical-align:top';
ob_start();
?>
<div style="padding:2rem 0;min-height:100vh">
  <div class="container-fluid" style="max-width:1300px">
    <h1 style="font-size:1.5rem;font-weight:900;margin-bottom:1rem">Reported Events</h1>
    <nav class="ne-tabs">
      <?php foreach (['open' => 'Open', 'reviewing' => 'Reviewing', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed', 'all' => 'All'] as $k => $l): ?>
        <a href="<?= $appUrl ?>/admin/reports?status=<?= $k ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </nav>

    <div style="background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius);overflow-x:auto">
      <table style="width:100%;border-collapse:collapse">
        <thead><tr style="background:var(--ne-bg-2)">
          <?php foreach (['Event', 'Reason', 'Details', 'Reporter', 'When', 'Status', ''] as $h): ?>
            <th style="padding:.75rem 1rem;text-align:left;font-size:.72rem;color:var(--ne-text-dim);text-transform:uppercase"><?= $h ?></th>
          <?php endforeach; ?>
        </tr></thead>
        <tbody>
          <?php if (!$reports): ?><tr><td colspan="7" style="<?= $td ?>;text-align:center;padding:2rem">No reports.</td></tr><?php endif; ?>
          <?php foreach ($reports as $r): ?>
            <tr style="border-top:1px solid var(--ne-border)">
              <td style="<?= $td ?>;max-width:240px">
                <a href="<?= $appUrl ?>/admin/events/<?= (int) $r['event_id'] ?>" style="color:var(--ne-text);font-weight:600"><?= View::e($r['event_title']) ?></a>
                <div style="font-size:.72rem;color:var(--ne-text-dim)"><?= View::e($r['event_status']) ?> · <?= View::e($r['moderation_status']) ?> · <?= (int) $r['event_open_reports'] ?> open</div>
              </td>
              <td style="<?= $td ?>"><?= View::e(ModerationService::REPORT_REASONS[$r['reason']] ?? $r['reason']) ?></td>
              <td style="<?= $td ?>;max-width:300px"><?= nl2br(View::e($r['description'] ?? '')) ?></td>
              <td style="<?= $td ?>"><?= View::e($r['reporter_name'] ?? '—') ?><div style="font-size:.72rem"><?= View::e($r['reporter_email'] ?? '') ?></div></td>
              <td style="<?= $td ?>;white-space:nowrap"><?= View::e($r['created_at']) ?></td>
              <td style="<?= $td ?>"><?= View::e($r['status']) ?><?= $r['resolution'] ? '<div style="font-size:.72rem">' . View::e($r['resolution']) . '</div>' : '' ?></td>
              <td style="<?= $td ?>;white-space:nowrap">
                <?php if (in_array($r['status'], ['open', 'reviewing'], true)): ?>
                  <form method="POST" action="<?= $appUrl ?>/admin/reports/<?= (int) $r['id'] ?>/resolve" style="display:flex;gap:.35rem;flex-wrap:wrap">
                    <?= View::csrf() ?>
                    <input type="text" name="resolution" placeholder="Note" class="ne-form-control" style="max-width:140px;padding:.35rem .6rem;font-size:.75rem">
                    <button name="status" value="resolved" class="btn-ne btn-ne-ghost btn-ne-sm">Resolve</button>
                    <button name="status" value="dismissed" class="btn-ne btn-ne-ghost btn-ne-sm">Dismiss</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/main.php';
?>
