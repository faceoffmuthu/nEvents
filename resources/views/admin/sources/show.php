<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$src    = $source ?? [];
ob_start();
?>
<div style="padding:2rem 0;min-height:100vh">
  <div class="container-xl" style="max-width:900px">

    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap">
      <a href="<?= $appUrl ?>/admin/sources" style="color:var(--ne-text-muted);text-decoration:none;font-size:.875rem">
        <i class="fas fa-arrow-left fa-xs"></i> Sources
      </a>
      <span style="color:var(--ne-border)">/</span>
      <span style="font-size:.875rem;color:var(--ne-text-muted)"><?= View::e($src['name'] ?? '') ?></span>
    </div>

    <div style="background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius);padding:1.5rem;margin-bottom:1.5rem">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem">
        <div>
          <h1 style="font-size:1.25rem;font-weight:800;margin-bottom:.25rem"><?= View::e($src['name'] ?? '') ?></h1>
          <div style="font-size:.8rem;color:var(--ne-text-muted)"><?= View::e($src['adapter_class'] ?? '') ?></div>
        </div>
        <div style="display:flex;gap:.5rem">
          <?php if ($src['enabled']): ?>
          <form method="POST" action="<?= $appUrl ?>/admin/sources/<?= $src['id'] ?>/run">
            <?= View::csrf() ?>
            <button type="submit" class="btn-ne btn-ne-primary btn-ne-sm"><i class="fas fa-play fa-xs"></i> Run Now</button>
          </form>
          <?php endif; ?>
          <form method="POST" action="<?= $appUrl ?>/admin/sources/<?= $src['id'] ?>/toggle">
            <?= View::csrf() ?>
            <button type="submit" class="btn-ne btn-ne-sm <?= $src['enabled'] ? 'btn-ne-ghost' : 'btn-ne-primary' ?>">
              <?= $src['enabled'] ? 'Disable' : 'Enable' ?>
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Recent runs -->
    <div style="background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius);overflow:hidden">
      <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--ne-border)">
        <h2 style="font-size:1rem;font-weight:700;margin:0">Recent Runs</h2>
      </div>
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr style="background:var(--ne-bg-2)">
              <?php foreach (['ID','Status','Started','Duration','Stats'] as $h): ?>
                <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:700;color:var(--ne-text-dim);text-transform:uppercase;letter-spacing:.05em;white-space:nowrap"><?= $h ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($runs ?? [] as $run): ?>
              <tr style="border-top:1px solid var(--ne-border)">
                <td style="padding:.75rem 1rem;font-size:.8rem;color:var(--ne-text-dim)"><?= $run['id'] ?></td>
                <td style="padding:.75rem 1rem">
                  <?php $c = match($run['status'] ?? '') { 'completed' => 'var(--ne-green)', 'failed' => 'var(--ne-accent)', default => 'var(--ne-text-muted)' }; ?>
                  <span class="badge-ne" style="background:var(--bg-surface);color:<?= $c ?>;border:1px solid <?= $c ?>;font-size:.7rem"><?= ucfirst($run['status'] ?? '') ?></span>
                </td>
                <td style="padding:.75rem 1rem;font-size:.8rem;color:var(--ne-text-muted);white-space:nowrap">
                  <?= !empty($run['started_at']) ? date('d M H:i', strtotime($run['started_at'])) : '—' ?>
                </td>
                <td style="padding:.75rem 1rem;font-size:.8rem;color:var(--ne-text-muted)">
                  <?php
                  if (!empty($run['started_at']) && !empty($run['completed_at'])) {
                      $secs = strtotime($run['completed_at']) - strtotime($run['started_at']);
                      echo $secs . 's';
                  } else { echo '—'; }
                  ?>
                </td>
                <td style="padding:.75rem 1rem;font-size:.8rem;color:var(--ne-text-muted)">
                  <?= (int)($run['records_new'] ?? 0) ?> new · <?= (int)($run['records_error'] ?? 0) ?> err
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
