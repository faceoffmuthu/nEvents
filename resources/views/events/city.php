<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$c      = $city ?? [];
ob_start();
?>
<div class="band-cream" style="min-height:100vh">
  <!-- City Hero -->
  <div class="band-sand ne-list-head" style="padding:3rem 0">
    <div class="container-xl">
      <nav aria-label="breadcrumb" style="margin-bottom:1rem">
        <ol class="breadcrumb" style="--bs-breadcrumb-divider-color:var(--ne-text-dim);font-size:.8rem">
          <li class="breadcrumb-item"><a href="<?= $appUrl ?>/" style="color:var(--ne-primary)">Home</a></li>
          <li class="breadcrumb-item active" style="color:var(--ne-text-muted)"><?= View::e($c['name'] ?? '') ?></li>
        </ol>
      </nav>
      <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap">
        <div class="ne-list-head-icon" style="width:64px;height:64px;background:var(--ne-grad-primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.75rem;flex-shrink:0">
          🏙️
        </div>
        <div>
          <h1 style="font-size:clamp(1.75rem,4vw,2.5rem);font-weight:900;margin-bottom:.25rem">
            Events in <span class="gradient-text"><?= View::e($c['name'] ?? '') ?></span>
          </h1>
          <p style="color:var(--ne-text-muted);margin:0"><?= number_format($total ?? 0) ?> upcoming events · <?= View::e($c['state_name'] ?? 'Tamil Nadu') ?></p>
        </div>
      </div>

      <!-- Category quick links -->
      <?php if (!empty($categories)): ?>
        <div class="ne-list-head-chips" style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1.5rem">
          <?php foreach (array_slice($categories, 0, 10) as $cat): ?>
            <a href="<?= $appUrl ?>/events/<?= View::e($c['slug'] ?? '') ?>/<?= View::e($cat['slug']) ?>"
               class="ne-category-chip" style="--cat-color:<?= View::e($cat['color'] ?? 'var(--ne-primary)') ?>">
              <i class="fas <?= View::e($cat['icon'] ?? 'fa-tag') ?> fa-xs"></i>
              <?= View::e($cat['name']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Events -->
  <section class="ne-section">
    <div class="container-xl">
      <?php if (empty($events)): ?>
        <div style="text-align:center;padding:5rem 2rem;background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius-lg)">
          <div class="ne-empty-icon" aria-hidden="true"><span></span><i class="fas fa-location-dot"></i></div>
          <h3 style="font-weight:700">No verified upcoming events found in <?= View::e($c['name'] ?? '') ?> right now.</h3>
          <p style="color:var(--ne-text-muted);max-width:420px;margin:.5rem auto 1.5rem">
            We only show genuine, verified events — never placeholders. Try another district, browse online events, or check back soon.
          </p>
          <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap">
            <a href="<?= $appUrl ?>/discover" class="btn-ne btn-ne-primary">Browse All Events Across India</a>
            <a href="<?= $appUrl ?>/events/online" class="btn-ne btn-ne-secondary">View Online Events</a>
            <a href="<?= $appUrl ?>/" class="btn-ne btn-ne-ghost">Change District</a>
          </div>
        </div>
      <?php else: ?>
        <?php $grid_events = $events; $grid_saved = false; include __DIR__ . '/../partials/event-grid.php'; ?>

        <?php if (($total ?? 0) > ($per_page ?? 20)): ?>
          <div class="ne-pagination">
            <?php
            $totalPages = ceil(($total ?? 0) / ($per_page ?? 20));
            for ($p = 1; $p <= min($totalPages, 10); $p++):
            ?>
              <a href="<?= $appUrl ?>/events/<?= View::e($c['slug'] ?? '') ?>?page=<?= $p ?>"
                 class="ne-page-btn <?= ($page ?? 1) === $p ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
