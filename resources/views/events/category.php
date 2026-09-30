<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$cat    = $category ?? [];
ob_start();
?>
<div class="band-cream" style="min-height:100vh">
  <!-- Header -->
  <div class="band-sand ne-list-head" style="padding:3rem 0">
    <div class="container-xl">
      <nav aria-label="breadcrumb" style="margin-bottom:1rem">
        <ol class="breadcrumb" style="--bs-breadcrumb-divider-color:var(--ne-text-dim);font-size:.8rem">
          <li class="breadcrumb-item"><a href="<?= $appUrl ?>/" style="color:var(--ne-primary)">Home</a></li>
          <li class="breadcrumb-item active" style="color:var(--ne-text-muted)"><?= View::e($cat['name'] ?? '') ?></li>
        </ol>
      </nav>
      <div style="display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap">
        <div class="ne-list-head-icon" style="width:56px;height:56px;background:<?= View::e($cat['color'] ?? 'var(--ne-primary)') ?>;border-radius:var(--ne-radius);display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;flex-shrink:0">
          <i class="fas <?= View::e($cat['icon'] ?? 'fa-tag') ?>"></i>
        </div>
        <div>
          <h1 style="font-size:clamp(1.75rem,4vw,2.5rem);font-weight:900;margin-bottom:.25rem">
            <span class="gradient-text"><?= View::e($cat['name'] ?? '') ?></span> Events
          </h1>
          <p style="color:var(--ne-text-muted);margin:0"><?= number_format($total ?? 0) ?> upcoming events</p>
        </div>
      </div>
    </div>
  </div>

  <section class="ne-section">
    <div class="container-xl">
      <?php if (empty($events)): ?>
        <div style="text-align:center;padding:5rem 2rem;background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius-lg)">
          <div class="ne-empty-icon" aria-hidden="true"><span></span><i class="fas fa-compass"></i></div>
          <h3 style="font-weight:700">No <?= View::e($cat['name'] ?? '') ?> events yet</h3>
          <p style="color:var(--ne-text-muted)">Events in this category will appear here.</p>
          <a href="<?= $appUrl ?>/discover" class="btn-ne btn-ne-primary mt-2">Browse All Events</a>
        </div>
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
