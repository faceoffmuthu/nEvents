<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
ob_start();
?>
<div style="padding:2rem 0;min-height:100vh">
  <div class="container-xl">

    <h1 style="font-size:1.5rem;font-weight:900;margin-bottom:.5rem">Event Sources</h1>
    <p style="color:var(--ne-text-muted);font-size:.85rem;margin-bottom:1.5rem;max-width:820px">
      Discovery runs in the background (<code>php bin/scheduler.php</code>), never on page load. A connector can only be enabled once its
      credentials or <code>config_json</code> are present. Social connectors use official APIs only — being visible in a browser
      doesn't mean a platform allows programmatic search.
    </p>

    <div style="background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius);overflow:hidden">
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr style="background:var(--ne-bg-2)">
              <?php foreach (['Name','Platform / Method','Configured','Active','Health','Trust','Runs','Last Run',''] as $h): ?>
                <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:700;color:var(--ne-text-dim);text-transform:uppercase;letter-spacing:.05em;white-space:nowrap"><?= $h ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($sources ?? [] as $src): ?>
              <tr style="border-top:1px solid var(--ne-border)">
                <td style="padding:.75rem 1rem;font-size:.875rem;font-weight:600">
                  <a href="<?= $appUrl ?>/admin/sources/<?= $src['id'] ?>" style="color:var(--ne-text);text-decoration:none"><?= View::e($src['name']) ?></a>
                </td>
                <td style="padding:.75rem 1rem;font-size:.8rem;color:var(--ne-text-muted);max-width:280px">
                  <?= View::e($src['meta']['platform']) ?> · <?= View::e($src['meta']['acquisition_method']) ?>
                  <div style="font-size:.72rem;color:var(--ne-text-dim)"><?= View::e($src['meta']['limitations']) ?></div>
                </td>
                <td style="padding:.75rem 1rem;font-size:.75rem">
                  <?php if ($src['meta']['configured']): ?>
                    <span style="color:var(--ne-green)"><i class="fas fa-check fa-xs"></i> Yes</span>
                  <?php else: ?>
                    <span style="color:var(--ne-gold)">Needs: <?= View::e(implode(', ', $src['meta']['credentials']) ?: 'config_json.url') ?></span>
                  <?php endif; ?>
                </td>
                <td style="padding:.75rem 1rem">
                  <?php $on = $src['enabled'] ?? 0; ?>
                  <span class="badge-ne" style="background:var(--bg-surface);color:<?= $on ? 'var(--ne-green)' : 'var(--ne-text-dim)' ?>;border:1px solid currentcolor;font-size:.7rem"><?= $on ? 'Active' : 'Off' ?></span>
                </td>
                <td style="padding:.75rem 1rem;font-size:.8rem;color:var(--ne-text-muted)"><?= View::e($src['health_status']) ?><?= (int) $src['error_count'] ? ' (' . (int) $src['error_count'] . ' errors)' : '' ?></td>
                <td style="padding:.75rem 1rem;font-size:.8rem;color:var(--ne-text-muted)"><?= (int) $src['trust_level'] ?></td>
                <td style="padding:.75rem 1rem;font-size:.8rem;color:var(--ne-text-muted)"><?= number_format($src['run_count'] ?? 0) ?></td>
                <td style="padding:.75rem 1rem;font-size:.8rem;color:var(--ne-text-muted);white-space:nowrap">
                  <?= !empty($src['last_run']) ? date('d M H:i', strtotime($src['last_run'])) : '—' ?>
                </td>
                <td style="padding:.75rem 1rem">
                  <form method="POST" action="<?= $appUrl ?>/admin/sources/<?= $src['id'] ?>/toggle" style="display:inline">
                    <?= View::csrf() ?>
                    <button type="submit" class="btn-ne btn-ne-ghost btn-ne-sm"><?= $on ? 'Disable' : 'Enable' ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/main.php';
?>
