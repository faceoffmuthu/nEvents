<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
ob_start();
?>
<div style="padding:2rem 0;min-height:100vh">
  <div class="container-fluid" style="max-width:1200px">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem">
      <h1 style="font-size:1.5rem;font-weight:900;margin:0">Districts
        <span style="font-size:1rem;font-weight:400;color:var(--ne-text-muted)">(<?= count($districts ?? []) ?> of <?= (int) ($total ?? 0) ?>)</span>
      </h1>
      <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
        <form method="GET" action="<?= $appUrl ?>/admin/districts">
          <select name="state" class="ne-form-control" onchange="this.form.submit()" aria-label="Filter by state or union territory">
            <option value="0">All states and union territories</option>
            <?php foreach ($states ?? [] as $st): ?>
              <option value="<?= (int) $st['id'] ?>" <?= (int) ($state_id ?? 0) === (int) $st['id'] ? 'selected' : '' ?>><?= View::e($st['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <a href="<?= $appUrl ?>/admin" class="btn-ne btn-ne-ghost btn-ne-sm">Back to Dashboard</a>
      </div>
    </div>

    <p style="color:var(--ne-text-muted);font-size:.875rem;margin-bottom:1.25rem">
      India's districts, by state and union territory, are the single source every district selector (homepage, dashboard, onboarding, search, event submission) reads from —
      there is exactly one list, not separate copies per page. Disabling a district here hides it everywhere immediately.
    </p>

    <div style="background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius);overflow:hidden">
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr style="background:var(--ne-bg-2)">
              <?php foreach (['ID','Name','State','Slug','Published Events','Status',''] as $h): ?>
                <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:700;color:var(--ne-text-dim);text-transform:uppercase;letter-spacing:.05em;white-space:nowrap"><?= $h ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($districts ?? [] as $d): ?>
              <tr style="border-top:1px solid var(--ne-border)">
                <td style="padding:.75rem 1rem;font-size:.8rem;color:var(--ne-text-dim)"><?= $d['id'] ?></td>
                <td style="padding:.75rem 1rem;font-size:.875rem;font-weight:600">
                  <a href="<?= $appUrl ?>/events/<?= View::e($d['slug']) ?>" target="_blank" style="color:var(--ne-text);text-decoration:none"><?= View::e($d['name']) ?></a>
                </td>
                <td style="padding:.75rem 1rem;font-size:.8rem;color:var(--ne-text-muted)"><?= View::e($d['state_name']) ?></td>
                <td style="padding:.75rem 1rem;font-size:.8rem;color:var(--ne-text-muted);font-family:monospace"><?= View::e($d['slug']) ?></td>
                <td style="padding:.75rem 1rem;font-size:.875rem;color:var(--ne-text-muted)"><?= number_format((int)$d['event_count']) ?></td>
                <td style="padding:.75rem 1rem">
                  <?php $on = (bool)$d['is_active']; ?>
                  <span class="badge-ne" style="background:var(--bg-surface);color:<?= $on ? 'var(--ne-green)' : 'var(--ne-text-dim)' ?>;border:1px solid currentcolor;font-size:.7rem"><?= $on ? 'Active' : 'Disabled' ?></span>
                </td>
                <td style="padding:.75rem 1rem">
                  <form method="POST" action="<?= $appUrl ?>/admin/districts/<?= $d['id'] ?>/toggle" style="display:inline">
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
