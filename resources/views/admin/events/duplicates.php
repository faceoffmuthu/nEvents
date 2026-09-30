<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
ob_start();
?>
<div style="padding:2rem 0;min-height:100vh">
  <div class="container-xl" style="max-width:1100px">
    <a href="<?= $appUrl ?>/admin/events" style="color:var(--ne-text-muted);text-decoration:none;font-size:.875rem"><i class="fas fa-arrow-left fa-xs"></i> Events</a>
    <h1 style="font-size:1.5rem;font-weight:900;margin:1rem 0 .5rem">Duplicate Candidates <span style="font-size:1rem;font-weight:400;color:var(--ne-text-muted)">(<?= count($pairs) ?>)</span></h1>
    <p style="color:var(--ne-text-muted);font-size:.875rem;margin-bottom:1.5rem">
      Pairs scored by DuplicateDetectionService that weren't certain enough to merge automatically. Merging keeps the chosen event,
      moves source references, saves and reports onto it, and archives the other (never deleted).
    </p>

    <?php if (!$pairs): ?>
      <div class="ne-card" style="padding:2rem;text-align:center;color:var(--ne-text-muted)">No duplicate candidates waiting for review.</div>
    <?php endif; ?>

    <?php foreach ($pairs as $p):
      $signals = json_decode((string) $p['signals_json'], true) ?: []; ?>
      <div class="ne-card" style="padding:1.25rem;margin-bottom:1rem">
        <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:.75rem">
          <strong>Score <?= (int) $p['score'] ?>/100</strong>
          <span style="font-size:.75rem;color:var(--ne-text-dim)"><?= View::e(implode(' · ', array_map(fn($k, $v) => $k . (is_bool($v) ? '' : ': ' . $v), array_keys($signals), $signals))) ?></span>
        </div>
        <div class="row g-3">
          <?php foreach (['a', 'b'] as $side): ?>
            <div class="col-md-6">
              <div style="border:1px solid var(--ne-border);border-radius:var(--ne-radius-sm);padding:1rem;height:100%">
                <a href="<?= $appUrl ?>/admin/events/<?= (int) $p["event_{$side}_id"] ?>" style="font-weight:700;color:var(--ne-text)"><?= View::e($p["{$side}_title"]) ?></a>
                <div style="font-size:.75rem;color:var(--ne-text-dim);margin:.25rem 0 .75rem">#<?= (int) $p["event_{$side}_id"] ?> · <?= View::e($p["{$side}_status"]) ?> · <?= View::e(str_replace('_', ' ', $p["{$side}_origin"])) ?></div>
                <form method="POST" action="<?= $appUrl ?>/admin/events/duplicates/<?= (int) $p['id'] ?>/merge" class="m-0">
                  <?= View::csrf() ?>
                  <input type="hidden" name="survivor_id" value="<?= (int) $p["event_{$side}_id"] ?>">
                  <button class="btn-ne btn-ne-primary btn-ne-sm" onclick="return confirm('Keep this event and archive the other?')">Keep this one &amp; merge</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <form method="POST" action="<?= $appUrl ?>/admin/events/duplicates/<?= (int) $p['id'] ?>/dismiss" class="mt-2">
          <?= View::csrf() ?>
          <button class="btn-ne btn-ne-ghost btn-ne-sm">Not a duplicate</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/main.php';
?>
