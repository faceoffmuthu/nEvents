<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
ob_start();
?>
<?= View::partial('page-header', ['title' => 'Saved <span class="gradient-text">Events</span>', 'subtitle' => 'Events you bookmarked — reminders are emailed before they start']) ?>
<section class="ne-section band-cream">
  <div class="container-xl">

    <?php if (empty($events)): ?>
      <div style="text-align:center;padding:5rem 2rem;background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius-lg)">
        <div class="ne-empty-icon" aria-hidden="true"><span></span><i class="fas fa-bookmark"></i></div>
        <h3 style="font-weight:700">No saved events</h3>
        <p style="color:var(--ne-text-muted)">Tap the bookmark icon on any event to save it here.</p>
        <a href="<?= $appUrl ?>/discover" class="btn-ne btn-ne-primary mt-2">Discover Events</a>
      </div>
    <?php else: ?>
      <?php $grid_events = $events; $grid_saved = true; include __DIR__ . '/../partials/event-grid.php'; ?>
    <?php endif; ?>
  </div>
</section>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
