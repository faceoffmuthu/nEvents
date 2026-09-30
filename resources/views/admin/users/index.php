<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
ob_start();
?>
<div style="padding:2rem 0;min-height:100vh">
  <div class="container-fluid" style="max-width:1400px">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
      <h1 style="font-size:1.5rem;font-weight:900;margin:0">Users
        <span style="font-size:1rem;font-weight:400;color:var(--ne-text-muted)">(<?= number_format($total ?? 0) ?>)</span>
      </h1>
    </div>

    <div style="background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius);overflow:hidden">
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr style="background:var(--ne-bg-2)">
              <?php foreach (['ID','Name','Email','Roles','Status','Verified','Joined',''] as $h): ?>
                <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:700;color:var(--ne-text-dim);text-transform:uppercase;letter-spacing:.05em;white-space:nowrap"><?= $h ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users ?? [] as $u): ?>
              <tr style="border-top:1px solid var(--ne-border)">
                <td style="padding:.75rem 1rem;font-size:.8rem;color:var(--ne-text-dim)"><?= $u['id'] ?></td>
                <td style="padding:.75rem 1rem;font-size:.875rem;font-weight:600"><?= View::e($u['name']) ?></td>
                <td style="padding:.75rem 1rem;font-size:.8rem;color:var(--ne-text-muted)"><?= View::e($u['email']) ?></td>
                <td style="padding:.75rem 1rem;font-size:.8rem;color:var(--ne-text-muted)"><?= View::e($u['roles'] ?? '—') ?></td>
                <td style="padding:.75rem 1rem">
                  <?php $c = $u['status'] === 'active' ? 'var(--ne-green)' : 'var(--ne-accent)'; ?>
                  <span class="badge-ne" style="background:var(--bg-surface);color:<?= $c ?>;border:1px solid <?= $c ?>;font-size:.7rem"><?= ucfirst($u['status']) ?></span>
                </td>
                <td style="padding:.75rem 1rem;font-size:.8rem;color:var(--ne-text-muted)"><?= $u['email_verified_at'] ? '✓' : '✗' ?></td>
                <td style="padding:.75rem 1rem;font-size:.8rem;color:var(--ne-text-muted);white-space:nowrap"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                <td style="padding:.75rem 1rem">
                  <?php if ($u['status'] === 'active'): ?>
                    <form method="POST" action="<?= $appUrl ?>/admin/users/<?= $u['id'] ?>/status" style="display:inline">
                      <?= View::csrf() ?>
                      <button name="status" value="suspended" class="btn-ne btn-ne-ghost btn-ne-sm">Suspend</button>
                    </form>
                  <?php else: ?>
                    <form method="POST" action="<?= $appUrl ?>/admin/users/<?= $u['id'] ?>/status" style="display:inline">
                      <?= View::csrf() ?>
                      <button name="status" value="active" class="btn-ne btn-ne-primary btn-ne-sm">Activate</button>
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
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/main.php';
?>
