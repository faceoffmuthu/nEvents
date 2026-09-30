<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$o      = $organizer ?? [];
$s      = $stats     ?? [];
ob_start();
?>
<div style="min-height:100vh;padding:2rem 0">
  <div class="container-xl">

    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem">
      <div>
        <div style="font-size:.7rem;color:var(--ne-text-dim);text-transform:uppercase;letter-spacing:.1em;margin-bottom:.25rem">Organizer Portal</div>
        <h1 style="font-size:1.5rem;font-weight:900;margin:0"><?= View::e($o['name'] ?? '') ?></h1>
      </div>
      <a href="<?= $appUrl ?>/organizer-portal/create-event" class="btn-ne btn-ne-primary">
        <i class="fas fa-plus fa-xs"></i> Add Event
      </a>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
      <?php foreach ([
          ['Total Events', $s['total_events'] ?? 0, 'fa-calendar', 'var(--ne-primary)'],
          ['Published',    $s['published_events'] ?? 0, 'fa-check-circle', 'var(--ne-green)'],
          ['Total Views',  $s['total_views'] ?? 0,  'fa-eye',      'var(--ne-secondary)'],
      ] as [$label, $val, $icon, $color]): ?>
        <div class="col-4">
          <div style="background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius);padding:1.25rem;text-align:center">
            <i class="fas <?= $icon ?>" style="color:<?= $color ?>;font-size:1.25rem;margin-bottom:.5rem;display:block"></i>
            <div style="font-size:1.75rem;font-weight:900"><?= number_format($val) ?></div>
            <div style="font-size:.75rem;color:var(--ne-text-dim)"><?= $label ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Recent events -->
    <div style="background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius);overflow:hidden">
      <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--ne-border);display:flex;justify-content:space-between;align-items:center">
        <h2 style="font-size:1rem;font-weight:700;margin:0">Recent Events</h2>
        <a href="<?= $appUrl ?>/organizer-portal/my-events" style="font-size:.8rem;color:var(--ne-primary)">View All</a>
      </div>
      <?php if (empty($events)): ?>
        <div style="padding:3rem;text-align:center;color:var(--ne-text-muted)">
          No events yet.
          <a href="<?= $appUrl ?>/organizer-portal/create-event" style="color:var(--ne-primary)">Add your first event</a>.
        </div>
      <?php else: ?>
        <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse">
            <thead>
              <tr style="background:var(--ne-bg-2)">
                <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:700;color:var(--ne-text-dim);text-transform:uppercase;letter-spacing:.05em">Title</th>
                <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:700;color:var(--ne-text-dim);text-transform:uppercase">Status</th>
                <th style="padding:.75rem 1rem;text-align:right;font-size:.75rem;font-weight:700;color:var(--ne-text-dim);text-transform:uppercase">Views</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($events as $ev): ?>
                <tr style="border-top:1px solid var(--ne-border)">
                  <td style="padding:.75rem 1rem;font-size:.875rem;font-weight:600">
                    <a href="<?= $appUrl ?>/event/<?= View::e($ev['slug']) ?>" target="_blank"
                       style="color:var(--ne-text);text-decoration:none"><?= View::e($ev['title']) ?></a>
                  </td>
                  <td style="padding:.75rem 1rem">
                    <span class="badge-ne" style="background:var(--bg-surface);color:<?= $ev['status'] === 'published' ? 'var(--ne-green)' : 'var(--ne-text-muted)' ?>;border:1px solid currentcolor;font-size:.7rem">
                      <?= ucfirst($ev['status']) ?>
                    </span>
                  </td>
                  <td style="padding:.75rem 1rem;text-align:right;font-size:.875rem;color:var(--ne-text-muted)"><?= number_format($ev['view_count']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
