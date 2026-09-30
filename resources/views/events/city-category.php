<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$c      = $city     ?? [];
$cat    = $category ?? [];
ob_start();
?>
<div class="band-cream" style="min-height:100vh">
  <div class="band-sand ne-list-head" style="padding:3rem 0">
    <div class="container-xl">
      <nav aria-label="breadcrumb" style="margin-bottom:1rem">
        <ol class="breadcrumb" style="--bs-breadcrumb-divider-color:var(--ne-text-dim);font-size:.8rem">
          <li class="breadcrumb-item"><a href="<?= $appUrl ?>/" style="color:var(--ne-primary)">Home</a></li>
          <li class="breadcrumb-item"><a href="<?= $appUrl ?>/events/<?= View::e($c['slug'] ?? '') ?>" style="color:var(--ne-secondary)"><?= View::e($c['name'] ?? '') ?></a></li>
          <li class="breadcrumb-item active" style="color:var(--ne-text-muted)"><?= View::e($cat['name'] ?? '') ?></li>
        </ol>
      </nav>
      <h1 style="font-size:clamp(1.5rem,4vw,2.25rem);font-weight:900;margin-bottom:.25rem">
        <span class="gradient-text"><?= View::e($cat['name'] ?? '') ?></span>
        Events in <span class="gradient-text"><?= View::e($c['name'] ?? '') ?></span>
      </h1>
      <p style="color:var(--ne-text-muted);margin:0"><?= number_format($total ?? 0) ?> upcoming events</p>
    </div>
  </div>

  <section class="ne-section">
    <div class="container-xl">
      <?php if (empty($events)): ?>
        <div style="text-align:center;padding:5rem 2rem;background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius-lg)">
          <div class="ne-empty-icon" aria-hidden="true"><span></span><i class="fas fa-magnifying-glass"></i></div>
          <h3 style="font-weight:700">No events found</h3>
          <p style="color:var(--ne-text-muted)">No <?= View::e($cat['name'] ?? '') ?> events in <?= View::e($c['name'] ?? '') ?> right now.</p>
          <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;margin-top:1.5rem">
            <a href="<?= $appUrl ?>/events/<?= View::e($c['slug'] ?? '') ?>" class="btn-ne btn-ne-secondary">All <?= View::e($c['name'] ?? '') ?> Events</a>
            <a href="<?= $appUrl ?>/discover" class="btn-ne btn-ne-ghost">Browse All</a>
          </div>
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
