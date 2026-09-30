<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$o = $organizer ?? [];
ob_start();
?>
<div class="band-cream" style="min-height:100vh">
  <!-- Organizer header -->
  <div class="band-sand" style="padding:3rem 0">
    <div class="container-xl">
      <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap">
        <?php if (!empty($o['logo_url'])): ?>
          <img src="<?= View::e($o['logo_url']) ?>" alt="<?= View::e($o['name']) ?>"
               style="width:80px;height:80px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid var(--ne-border)">
        <?php else: ?>
          <div style="width:80px;height:80px;border-radius:50%;background:var(--ne-grad-primary);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:900;color:#fff;flex-shrink:0">
            <?= strtoupper(substr($o['name'] ?? 'O', 0, 1)) ?>
          </div>
        <?php endif; ?>
        <div>
          <h1 style="font-size:clamp(1.5rem,4vw,2rem);font-weight:900;margin-bottom:.25rem"><?= View::e($o['name'] ?? '') ?></h1>
          <?php if (!empty($o['city_name'])): ?>
            <p style="color:var(--ne-text-muted);margin:0;font-size:.875rem"><i class="fas fa-map-marker-alt fa-xs"></i> <?= View::e($o['city_name']) ?></p>
          <?php endif; ?>
          <?php if (!empty($o['website_url'])): ?>
            <a href="<?= View::e($o['website_url']) ?>" target="_blank" rel="noopener noreferrer"
               style="font-size:.8rem;color:var(--ne-secondary);text-decoration:none;margin-top:.25rem;display:inline-block">
              <i class="fas fa-external-link-alt fa-xs"></i> Website
            </a>
          <?php endif; ?>
        </div>
      </div>
      <?php if (!empty($o['description'])): ?>
        <p style="color:var(--ne-text-muted);margin-top:1.25rem;max-width:640px;line-height:1.7"><?= nl2br(View::e($o['description'])) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Events -->
  <section class="ne-section">
    <div class="container-xl">
      <h2 class="ne-section-title" style="margin-bottom:1.5rem">Events by <?= View::e($o['name'] ?? '') ?></h2>
      <?php if (empty($events)): ?>
        <p style="color:var(--ne-text-muted)">No events listed yet.</p>
      <?php else: ?>
        <?php $grid_events = $events; $grid_saved = false; include __DIR__ . '/../partials/event-grid.php'; ?>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
