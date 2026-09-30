<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$o = $organizer ?? [];
ob_start();
?>
<div style="min-height:100vh;padding:2rem 0">
  <div class="container-xl">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem">
      <div>
        <div style="font-size:.7rem;color:var(--ne-text-dim);text-transform:uppercase;letter-spacing:.1em;margin-bottom:.25rem">Organizer Portal</div>
        <h1 style="font-size:1.5rem;font-weight:900;margin:0">My Events</h1>
      </div>
      <a href="<?= $appUrl ?>/organizer-portal/create-event" class="btn-ne btn-ne-primary">
        <i class="fas fa-plus fa-xs"></i> Add Event
      </a>
    </div>

    <?php if (empty($events)): ?>
      <div style="text-align:center;padding:4rem 2rem;background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius-lg)">
        <div class="ne-empty-icon" aria-hidden="true"><span></span><i class="fas fa-calendar-plus"></i></div>
        <h3 style="font-weight:700">No events yet</h3>
        <p style="color:var(--ne-text-muted)">Submit your first event to get started.</p>
        <a href="<?= $appUrl ?>/organizer-portal/create-event" class="btn-ne btn-ne-primary mt-2">Add Event</a>
      </div>
    <?php else: ?>
      <div style="background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius);overflow:hidden">
        <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse">
            <thead>
              <tr style="background:var(--ne-bg-2)">
                <?php foreach (['Title','Status','Views','Clicks','Free','Added'] as $h): ?>
                  <th style="padding:.75rem 1rem;text-align:left;font-size:.75rem;font-weight:700;color:var(--ne-text-dim);text-transform:uppercase;letter-spacing:.05em;white-space:nowrap"><?= $h ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($events as $ev): ?>
                <tr style="border-top:1px solid var(--ne-border)">
                  <td style="padding:.75rem 1rem;font-size:.875rem;font-weight:600;max-width:220px">
                    <a href="<?= $appUrl ?>/event/<?= View::e($ev['slug']) ?>" target="_blank"
                       style="color:var(--ne-text);text-decoration:none"><?= View::e($ev['title']) ?></a>
                  </td>
                  <td style="padding:.75rem 1rem">
                    <span class="badge-ne" style="background:var(--bg-surface);color:<?= $ev['status']==='published'?'var(--ne-green)':'var(--ne-text-muted)' ?>;border:1px solid currentcolor;font-size:.7rem"><?= ucfirst($ev['status']) ?></span>
                  </td>
                  <td style="padding:.75rem 1rem;font-size:.875rem;color:var(--ne-text-muted)"><?= number_format($ev['view_count']) ?></td>
                  <td style="padding:.75rem 1rem;font-size:.875rem;color:var(--ne-text-muted)"><?= number_format($ev['click_count']) ?></td>
                  <td style="padding:.75rem 1rem;font-size:.875rem"><?= $ev['pricing_type'] === 'free' ? '<span style="color:var(--ne-green)">Free</span>' : 'Paid' ?></td>
                  <td style="padding:.75rem 1rem;font-size:.8rem;color:var(--ne-text-muted);white-space:nowrap"><?= date('d M Y', strtotime($ev['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
