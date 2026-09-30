<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$o = $organizer ?? [];
ob_start();
?>
<div style="min-height:100vh;padding:2rem 0">
  <div class="container-xl" style="max-width:700px">
    <div style="margin-bottom:1.5rem">
      <div style="font-size:.7rem;color:var(--ne-text-dim);text-transform:uppercase;letter-spacing:.1em;margin-bottom:.25rem">Organizer Portal</div>
      <h1 style="font-size:1.5rem;font-weight:900;margin:0">Profile &amp; Settings</h1>
    </div>

    <div style="background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius);padding:1.5rem;margin-bottom:1rem">
      <h2 style="font-size:1rem;font-weight:700;margin-bottom:1rem">Organizer Details</h2>
      <div class="row g-3">
        <?php foreach ([
            ['Name',    $o['name']     ?? ''],
            ['Slug',    $o['slug']     ?? ''],
            ['Status',  ucfirst($o['status'] ?? '')],
            ['Website', $o['website_url'] ?? '—'],
            ['Email',   $o['email']    ?? '—'],
        ] as [$label, $val]): ?>
          <div class="col-sm-6">
            <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--ne-text-dim);margin-bottom:.25rem"><?= $label ?></div>
            <div style="font-weight:600;font-size:.9rem"><?= View::e((string)$val) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="ne-alert ne-alert-info">
      <i class="fas fa-info-circle"></i>
      <span>To update your organizer profile, contact us at <strong>organizers@nevents.in</strong>.</span>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
