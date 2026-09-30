<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$qs = fn(array $over) => http_build_query(array_filter(array_merge([
    'view' => $view ?? '', 'status' => $status ?? '', 'district_id' => $district_id ?? 0, 'source' => $source ?? '', 'q' => $q ?? '',
], $over), fn($v) => $v !== '' && $v !== 0 && $v !== '0'));
$th = 'padding:.75rem 1rem;text-align:left;font-size:.72rem;font-weight:700;color:var(--ne-text-dim);text-transform:uppercase;letter-spacing:.05em;white-space:nowrap';
$td = 'padding:.7rem 1rem;font-size:.8rem;color:var(--ne-text-muted);vertical-align:top';
ob_start();
?>
<div style="padding:2rem 0;min-height:100vh">
  <div class="container-fluid" style="max-width:1500px">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:1rem">
      <h1 style="font-size:1.5rem;font-weight:900;margin:0">Events
        <span style="font-size:1rem;font-weight:400;color:var(--ne-text-muted)">(<?= number_format($total ?? 0) ?>)</span>
      </h1>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <a href="<?= $appUrl ?>/admin/events/duplicates" class="btn-ne btn-ne-ghost btn-ne-sm"><i class="fas fa-clone fa-xs"></i> Duplicate Candidates</a>
        <a href="<?= $appUrl ?>/admin/reports" class="btn-ne btn-ne-ghost btn-ne-sm"><i class="fas fa-flag fa-xs"></i> Reports</a>
        <a href="<?= $appUrl ?>/admin/sources" class="btn-ne btn-ne-ghost btn-ne-sm"><i class="fas fa-plug fa-xs"></i> Source Management</a>
      </div>
    </div>

    <nav class="ne-tabs">
      <?php foreach ($views as $key => $label): ?>
        <a href="<?= $appUrl ?>/admin/events?<?= $qs(['view' => $key, 'page' => '']) ?>" class="<?= ($view ?? '') === $key ? 'active' : '' ?>"><?= View::e($label) ?></a>
      <?php endforeach; ?>
    </nav>

    <form method="GET" action="<?= $appUrl ?>/admin/events" style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.25rem">
      <input type="hidden" name="view" value="<?= View::e($view ?? '') ?>">
      <input type="search" name="q" value="<?= View::e($q ?? '') ?>" placeholder="Title or submitter email" class="ne-form-control" style="max-width:240px">
      <select name="status" class="ne-form-control" style="max-width:170px" onchange="this.form.submit()">
        <option value="">Any status</option>
        <?php foreach (['published', 'pending', 'draft', 'cancelled', 'completed', 'rejected', 'archived'] as $s): ?>
          <option value="<?= $s ?>" <?= ($status ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="district_id" class="ne-form-control" style="max-width:200px" onchange="this.form.submit()">
        <option value="0">All districts</option>
        <?php foreach ($districts ?? [] as $d): ?>
          <option value="<?= (int) $d['id'] ?>" <?= (int) ($district_id ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= View::e($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="source" class="ne-form-control" style="max-width:200px" onchange="this.form.submit()">
        <option value="">All sources</option>
        <?php foreach ($sources as $val => $label): ?>
          <option value="<?= $val ?>" <?= ($source ?? '') === $val ? 'selected' : '' ?>><?= View::e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn-ne btn-ne-ghost btn-ne-sm" type="submit">Filter</button>
    </form>

    <div style="background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius);overflow:hidden">
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr style="background:var(--ne-bg-2)">
              <?php foreach (['Event', 'Submitter', 'District', 'Category', 'Date', 'Created', 'Origin', 'Status', 'Reports', ''] as $h): ?>
                <th style="<?= $th ?>"><?= $h ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($events)): ?>
              <tr><td colspan="10" style="<?= $td ?>;text-align:center;padding:2rem">No events match these filters.</td></tr>
            <?php endif; ?>
            <?php foreach ($events ?? [] as $ev):
              $sc = match ($ev['status']) { 'published' => 'var(--ne-green)', 'pending' => 'var(--ne-gold)', 'rejected', 'cancelled' => 'var(--ne-accent)', default => 'var(--ne-text-dim)' };
            ?>
              <tr style="border-top:1px solid var(--ne-border)">
                <td style="<?= $td ?>;max-width:260px">
                  <a href="<?= $appUrl ?>/admin/events/<?= (int) $ev['id'] ?>" style="color:var(--ne-text);font-weight:600;text-decoration:none"><?= View::e($ev['title']) ?></a>
                  <div style="font-size:.72rem;color:var(--ne-text-dim)">#<?= (int) $ev['id'] ?> · <?= View::e($ev['organizer_name'] ?? '—') ?></div>
                </td>
                <td style="<?= $td ?>">
                  <?php if ($ev['submitter_name']): ?>
                    <?= View::e($ev['submitter_name']) ?>
                    <div style="font-size:.72rem;color:var(--ne-text-dim)"><?= View::e($ev['submitter_email']) ?></div>
                    <?php if (!(int) $ev['can_post_events']): ?><span class="ne-status-pill ne-status-rejected">posting suspended</span><?php endif; ?>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <td style="<?= $td ?>"><?= View::e($ev['format'] === 'online' ? 'Online' : ($ev['district_name'] ?? '—')) ?></td>
                <td style="<?= $td ?>"><?= View::e($ev['category_name'] ?? '—') ?></td>
                <td style="<?= $td ?>;white-space:nowrap"><?= $ev['next_start'] ? (new DateTimeImmutable($ev['next_start'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y H:i') : '—' ?></td>
                <td style="<?= $td ?>;white-space:nowrap"><?= date('d M Y', strtotime($ev['created_at'])) ?></td>
                <td style="<?= $td ?>">
                  <span class="badge-ne" style="font-size:.65rem;background:var(--bg-surface);color:var(--ne-secondary);border:1px solid var(--ne-secondary)"><?= View::e(str_replace('_', ' ', $ev['data_origin'])) ?></span>
                  <?php if ($ev['platforms']): ?><div style="font-size:.7rem;color:var(--ne-text-dim);margin-top:.25rem"><?= View::e($ev['platforms']) ?></div><?php endif; ?>
                </td>
                <td style="<?= $td ?>">
                  <span class="badge-ne" style="font-size:.68rem;background:var(--bg-surface);color:<?= $sc ?>;border:1px solid <?= $sc ?>"><?= View::e(ucfirst($ev['status'])) ?></span>
                  <?php if ($ev['moderation_status'] !== 'clean'): ?>
                    <div style="font-size:.7rem;color:var(--ne-gold);margin-top:.25rem" title="<?= View::e($ev['moderation_reason'] ?? '') ?>"><?= View::e(str_replace('_', ' ', $ev['moderation_status'])) ?></div>
                  <?php endif; ?>
                </td>
                <td style="<?= $td ?>"><?= (int) $ev['open_reports'] ?: '—' ?></td>
                <td style="<?= $td ?>;white-space:nowrap">
                  <a href="<?= $appUrl ?>/admin/events/<?= (int) $ev['id'] ?>" class="btn-ne btn-ne-ghost btn-ne-sm">Manage</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php $pages = (int) ceil(($total ?? 0) / ($per_page ?? 25)); if ($pages > 1): ?>
      <div style="display:flex;gap:.35rem;margin-top:1rem;flex-wrap:wrap">
        <?php for ($p = 1; $p <= min($pages, 30); $p++): ?>
          <a href="<?= $appUrl ?>/admin/events?<?= $qs(['page' => $p]) ?>" class="btn-ne btn-ne-sm <?= $p === ($page ?? 1) ? 'btn-ne-primary' : 'btn-ne-ghost' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>

  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/main.php';
?>
