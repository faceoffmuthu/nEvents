<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$s      = $stats ?? [];
ob_start();
?>
<div style="min-height:100vh;padding:2rem 0">
  <div class="container-fluid" style="max-width:1400px">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem">
      <div>
        <h1 style="font-size:1.5rem;font-weight:900;margin-bottom:.2rem">Admin Dashboard</h1>
        <p style="color:var(--ne-text-muted);margin:0;font-size:.85rem">N Events · <?= date('D, d M Y') ?></p>
      </div>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <a href="<?= $appUrl ?>/admin/events" class="btn-ne btn-ne-ghost btn-ne-sm">Events</a>
        <a href="<?= $appUrl ?>/admin/users" class="btn-ne btn-ne-ghost btn-ne-sm">Users</a>
        <a href="<?= $appUrl ?>/admin/sources" class="btn-ne btn-ne-ghost btn-ne-sm">Sources</a>
        <a href="<?= $appUrl ?>/admin/districts" class="btn-ne btn-ne-ghost btn-ne-sm">Districts</a>
      </div>
    </div>

    <!-- Stats row -->
    <div class="row g-3 mb-4">
      <?php
      $statCards = [
          ['Total Events',      $s['total_events']     ?? 0, 'fa-calendar-alt', 'var(--ne-primary)'],
          ['Published',         $s['published_events'] ?? 0, 'fa-check-circle', 'var(--ne-green)'],
          ['Users',             $s['total_users']      ?? 0, 'fa-users',        'var(--ne-secondary)'],
          ['Organizers',        $s['total_organizers'] ?? 0, 'fa-building',     'var(--ne-gold)'],
          ['Pending Reports',   $s['pending_reports']  ?? 0, 'fa-flag',         'var(--ne-accent)'],
          ['Queued Jobs',       $s['pending_jobs']     ?? 0, 'fa-cogs',         'var(--ne-text-muted)'],
      ];
      foreach ($statCards as [$label, $value, $icon, $color]):
      ?>
        <div class="col-6 col-lg-2">
          <div style="background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius);padding:1.25rem;text-align:center">
            <i class="fas <?= $icon ?>" style="font-size:1.25rem;color:<?= $color ?>;margin-bottom:.5rem;display:block"></i>
            <div style="font-size:1.75rem;font-weight:900;color:var(--ne-text)"><?= number_format($value) ?></div>
            <div style="font-size:.75rem;color:var(--ne-text-dim)"><?= $label ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Recent events table -->
    <div style="background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius);overflow:hidden">
      <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--ne-border);display:flex;justify-content:space-between;align-items:center">
        <h2 style="font-size:1rem;font-weight:700;margin:0">Recent Events</h2>
        <a href="<?= $appUrl ?>/admin/events" style="font-size:.8rem;color:var(--ne-primary)">View All</a>
      </div>
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr style="background:var(--ne-bg-2)">
              <?php foreach (['Title', 'Organizer', 'Status', 'Added'] as $h): ?>
                <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:700;color:var(--ne-text-dim);text-transform:uppercase;letter-spacing:.05em;white-space:nowrap"><?= $h ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentEvents ?? [] as $ev): ?>
              <tr style="border-top:1px solid var(--ne-border)">
                <td style="padding:.75rem 1rem;font-size:.875rem;font-weight:600;max-width:250px">
                  <a href="<?= $appUrl ?>/admin/events/<?= $ev['id'] ?>" style="color:var(--ne-text);text-decoration:none">
                    <?= View::e($ev['title']) ?>
                  </a>
                </td>
                <td style="padding:.75rem 1rem;font-size:.8rem;color:var(--ne-text-muted)"><?= View::e($ev['organizer_name'] ?? '—') ?></td>
                <td style="padding:.75rem 1rem">
                  <?php
                  $badge = match($ev['status'] ?? '') {
                      'published' => ['Published', 'var(--ne-green)'],
                      'draft'     => ['Draft',     'var(--ne-text-dim)'],
                      'rejected'  => ['Rejected',  'var(--ne-accent)'],
                      default     => [ucfirst($ev['status'] ?? ''), 'var(--ne-text-muted)'],
                  };
                  ?>
                  <span class="badge-ne" style="background:var(--bg-surface);color:<?= $badge[1] ?>;border:1px solid <?= $badge[1] ?>"><?= $badge[0] ?></span>
                </td>
                <td style="padding:.75rem 1rem;font-size:.8rem;color:var(--ne-text-muted);white-space:nowrap">
                  <?= date('d M Y', strtotime($ev['created_at'])) ?>
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
include __DIR__ . '/../layouts/main.php';
?>
