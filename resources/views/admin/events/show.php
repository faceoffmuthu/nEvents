<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$e = $event ?? [];
$card = 'background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius);padding:1.5rem;margin-bottom:1.25rem';
$label = 'font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:var(--ne-text-dim);margin-bottom:.25rem';
ob_start();
?>
<div style="padding:2rem 0;min-height:100vh">
  <div class="container-xl" style="max-width:1000px">
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap">
      <a href="<?= $appUrl ?>/admin/events" style="color:var(--ne-text-muted);text-decoration:none;font-size:.875rem"><i class="fas fa-arrow-left fa-xs"></i> Events</a>
      <span style="color:var(--ne-border)">/</span>
      <span style="font-size:.875rem;color:var(--ne-text-muted)">#<?= (int) $e['id'] ?></span>
    </div>

    <div style="<?= $card ?>">
      <h1 style="font-size:1.3rem;font-weight:800;margin-bottom:1rem"><?= View::e($e['title']) ?></h1>
      <div class="row g-3">
        <?php
        $fields = [
            ['Status', ucfirst($e['status'])],
            ['Moderation', str_replace('_', ' ', $e['moderation_status']) . ($e['moderation_reason'] ? ' — ' . $e['moderation_reason'] : '')],
            ['Origin', str_replace('_', ' ', $e['data_origin'])],
            ['Format', ucfirst($e['format'])],
            ['District', $e['district_name'] ?? '—'],
            ['Starts', $e['next_start'] ? (new DateTimeImmutable($e['next_start'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y H:i') . ' IST' : '—'],
            ['Views / Clicks / Saves', number_format((int) $e['view_count']) . ' / ' . number_format((int) $e['click_count']) . ' / ' . number_format((int) $e['save_count'])],
            ['Created', date('d M Y H:i', strtotime($e['created_at']))],
            ['Submitter', $e['submitter_name'] ? $e['submitter_name'] . ' <' . $e['submitter_email'] . '>' : '—'],
        ];
        foreach ($fields as [$l, $val]): ?>
          <div class="col-sm-6 col-md-4">
            <div style="<?= $label ?>"><?= $l ?></div>
            <div style="font-size:.88rem;font-weight:600;overflow-wrap:anywhere"><?= View::e((string) $val) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap">
        <?php if (in_array($e['status'], ['published', 'cancelled', 'completed', 'postponed'], true)): ?>
          <a href="<?= $appUrl ?>/event/<?= View::e($e['slug']) ?>" target="_blank" class="btn-ne btn-ne-ghost btn-ne-sm"><i class="fas fa-eye fa-xs"></i> View public page</a>
        <?php endif; ?>
        <a href="<?= $appUrl ?>/my-events/<?= (int) $e['id'] ?>/edit" class="btn-ne btn-ne-ghost btn-ne-sm"><i class="fas fa-pen fa-xs"></i> Edit</a>
      </div>
    </div>

    <!-- Moderation -->
    <div style="<?= $card ?>">
      <h2 style="font-size:1rem;font-weight:700;margin-bottom:1rem">Moderation</h2>
      <form method="POST" action="<?= $appUrl ?>/admin/events/<?= (int) $e['id'] ?>/moderate">
        <?= View::csrf() ?>
        <div class="ne-form-group">
          <label class="ne-form-label" for="mod-reason">Reason (sent to the submitter for unpublish / reject)</label>
          <input id="mod-reason" type="text" name="reason" maxlength="400" class="ne-form-control" placeholder="e.g. Registration link points to an unrelated site">
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
          <?php
          $actions = [
              'publish' => ['Publish', 'btn-ne-primary'], 'unpublish' => ['Unpublish', 'btn-ne-ghost'],
              'flag' => ['Flag', 'btn-ne-ghost'], 'unflag' => ['Clear flag', 'btn-ne-ghost'],
              'reject' => ['Reject', 'btn-ne-ghost'], 'suspend' => ['Suspend event', 'btn-ne-ghost'], 'cancel' => ['Cancel event', 'btn-ne-ghost'],
          ];
          foreach ($actions as $act => [$lbl, $cls]): ?>
            <button type="submit" name="action" value="<?= $act ?>" class="btn-ne btn-ne-sm <?= $cls ?>"
              <?= in_array($act, ['reject', 'suspend', 'cancel'], true) ? 'onclick="return confirm(\'' . $lbl . '?\')"' : '' ?>><?= $lbl ?></button>
          <?php endforeach; ?>
        </div>
      </form>

      <?php if ($e['created_by_user_id']): ?>
        <hr class="ne-divider">
        <form method="POST" action="<?= $appUrl ?>/admin/users/<?= (int) $e['created_by_user_id'] ?>/posting" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
          <?= View::csrf() ?>
          <input type="hidden" name="event_id" value="<?= (int) $e['id'] ?>">
          <input type="hidden" name="allow" value="<?= (int) $e['can_post_events'] ? '0' : '1' ?>">
          <span style="font-size:.85rem;color:var(--ne-text-muted)">Submitter can post events: <strong><?= (int) $e['can_post_events'] ? 'Yes' : 'No (suspended)' ?></strong></span>
          <input type="text" name="reason" class="ne-form-control" placeholder="Reason" style="max-width:240px">
          <button type="submit" class="btn-ne btn-ne-ghost btn-ne-sm"><?= (int) $e['can_post_events'] ? 'Suspend submitter from posting' : 'Reinstate posting' ?></button>
        </form>
      <?php endif; ?>
    </div>

    <!-- Sources -->
    <div style="<?= $card ?>">
      <h2 style="font-size:1rem;font-weight:700;margin-bottom:1rem">Source references (<?= count($sources) ?>)</h2>
      <?php if (!$sources): ?><p style="color:var(--ne-text-dim);font-size:.85rem;margin:0">No external sources — created on the platform.</p><?php endif; ?>
      <?php foreach ($sources as $s): ?>
        <div style="font-size:.82rem;padding:.5rem 0;border-top:1px solid var(--ne-border)">
          <strong><?= View::e($s['source_name']) ?></strong> (<?= View::e($s['platform']) ?>)<?= $s['is_primary'] ? ' · primary' : '' ?>
          · <?= View::e($s['source_status']) ?> · confidence <?= (int) $s['confidence'] ?>
          <?php if ($s['account_handle']): ?> · @<?= View::e($s['account_handle']) ?><?php endif; ?>
          <?php if ($s['source_url']): ?><div><a href="<?= View::e($s['source_url']) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--ne-primary);overflow-wrap:anywhere"><?= View::e($s['source_url']) ?></a></div><?php endif; ?>
          <div style="color:var(--ne-text-dim)">first seen <?= View::e($s['first_seen_at'] ?? '—') ?> · last seen <?= View::e($s['last_seen_at'] ?? '—') ?> · last checked <?= View::e($s['last_checked_at'] ?? '—') ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Source conflicts (EventMergeService) -->
    <div style="<?= $card ?>">
      <h2 style="font-size:1rem;font-weight:700;margin-bottom:.35rem">Source conflicts (<?= count(array_filter($conflicts ?? [], fn($c) => $c['status'] === 'open')) ?> open)</h2>
      <p style="font-size:.8rem;color:var(--ne-text-dim);margin-bottom:.75rem">A source reported a different value but wasn't trusted enough to overwrite it (or the event was posted by a person). Edit the event if the new value is right.</p>
      <?php if (empty($conflicts)): ?><p style="color:var(--ne-text-dim);font-size:.85rem;margin:0">No conflicts.</p><?php endif; ?>
      <?php foreach ($conflicts ?? [] as $cf): ?>
        <div style="font-size:.82rem;padding:.5rem 0;border-top:1px solid var(--ne-border);display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap">
          <div style="min-width:0;overflow-wrap:anywhere">
            <strong><?= View::e(str_replace('_', ' ', $cf['field'])) ?></strong> · <?= View::e($cf['status']) ?> · <?= View::e($cf['created_at']) ?><br>
            current: <span style="color:var(--ne-text)"><?= View::e($cf['value_a'] ?: '—') ?></span> <span style="color:var(--ne-text-dim)">(<?= View::e($cf['source_a'] ?? 'posted') ?>)</span><br>
            reported: <span style="color:var(--brand-accent-strong)"><?= View::e($cf['value_b']) ?></span> <span style="color:var(--ne-text-dim)">(<?= View::e($cf['source_b'] ?? '?') ?>)</span>
          </div>
          <?php if ($cf['status'] === 'open'): ?>
            <form method="POST" action="<?= $appUrl ?>/admin/conflicts/<?= (int) $cf['id'] ?>/resolve" style="display:flex;gap:.35rem;align-items:flex-start">
              <?= View::csrf() ?>
              <button name="status" value="resolved" class="btn-ne btn-ne-ghost btn-ne-sm">Resolved</button>
              <button name="status" value="dismissed" class="btn-ne btn-ne-ghost btn-ne-sm">Dismiss</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Reports -->
    <div style="<?= $card ?>">
      <h2 style="font-size:1rem;font-weight:700;margin-bottom:1rem">Reports (<?= count($reports) ?>)</h2>
      <?php if (!$reports): ?><p style="color:var(--ne-text-dim);font-size:.85rem;margin:0">No reports.</p><?php endif; ?>
      <?php foreach ($reports as $r): ?>
        <div style="font-size:.82rem;padding:.5rem 0;border-top:1px solid var(--ne-border);display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap">
          <div>
            <strong><?= View::e(\NEvents\Services\Moderation\ModerationService::REPORT_REASONS[$r['reason']] ?? $r['reason']) ?></strong>
            · <?= View::e($r['status']) ?> · by <?= View::e($r['reporter_name'] ?? 'unknown') ?> · <?= View::e($r['created_at']) ?>
            <?php if ($r['description']): ?><div style="color:var(--ne-text-muted)"><?= nl2br(View::e($r['description'])) ?></div><?php endif; ?>
          </div>
          <?php if (in_array($r['status'], ['open', 'reviewing'], true)): ?>
            <form method="POST" action="<?= $appUrl ?>/admin/reports/<?= (int) $r['id'] ?>/resolve" style="display:flex;gap:.35rem">
              <?= View::csrf() ?>
              <input type="hidden" name="back" value="event">
              <button name="status" value="resolved" class="btn-ne btn-ne-ghost btn-ne-sm">Resolve</button>
              <button name="status" value="dismissed" class="btn-ne btn-ne-ghost btn-ne-sm">Dismiss</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Audit -->
    <div style="<?= $card ?>">
      <h2 style="font-size:1rem;font-weight:700;margin-bottom:1rem">Audit history</h2>
      <?php foreach ($history as $h): ?>
        <div style="font-size:.8rem;padding:.35rem 0;border-top:1px solid var(--ne-border);color:var(--ne-text-muted)">
          <?= View::e($h['created_at']) ?> · <strong><?= View::e($h['action']) ?></strong> · <?= View::e($h['actor_name'] ?? $h['actor_type']) ?> — <?= View::e($h['summary']) ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$history): ?><p style="color:var(--ne-text-dim);font-size:.85rem;margin:0">No audit entries.</p><?php endif; ?>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/main.php';
?>
