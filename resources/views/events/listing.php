<?php
use NEvents\Core\View;
$appUrl  = rtrim($_ENV['APP_URL'] ?? '', '/');
$heading = $heading ?? 'Events';
ob_start();
?>
<?php ob_start(); ?>
      <div class="ne-date-tabs">
        <a href="<?= $appUrl ?>/events/today"        class="ne-date-tab">Today</a>
        <a href="<?= $appUrl ?>/events/tomorrow"     class="ne-date-tab">Tomorrow</a>
        <a href="<?= $appUrl ?>/events/this-weekend" class="ne-date-tab">Weekend</a>
        <a href="<?= $appUrl ?>/events/this-week"    class="ne-date-tab">This Week</a>
        <a href="<?= $appUrl ?>/events/free"         class="ne-date-tab">Free</a>
        <a href="<?= $appUrl ?>/events/online"       class="ne-date-tab">Online</a>
      </div>
<?php $tabs = ob_get_clean(); ?>
<?= View::partial('page-header', ['title' => View::e($heading), 'subtitle' => !empty($total) ? number_format($total) . ' events found' : null, 'actions' => $tabs]) ?>
<section class="ne-section band-cream">
  <div class="container-xl">

    <?php if (empty($events)): ?>
      <div style="text-align:center;padding:5rem 2rem;background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius-lg)">
        <div class="ne-empty-icon" aria-hidden="true"><span></span><i class="fas fa-magnifying-glass"></i></div>
        <h3 style="font-weight:700;color:var(--ne-text)">No Events Found</h3>
        <p style="color:var(--ne-text-muted);max-width:400px;margin:.75rem auto 2rem">
          No events match your current filters. Try adjusting your date range or checking a different category.
        </p>
        <a href="<?= $appUrl ?>/discover" class="btn-ne btn-ne-primary">Browse All Events</a>
      </div>
    <?php else: ?>
      <?php $grid_events = $events; $grid_saved = false; include __DIR__ . '/../partials/event-grid.php'; ?>
    <?php endif; ?>
  </div>
</section>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
