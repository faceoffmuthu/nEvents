<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
ob_start();
?>
<div class="band-cream" style="min-height:100vh">
  <div class="band-sand ne-list-head" style="padding:2.5rem 0">
    <div class="container-xl">
      <h1 style="font-size:clamp(1.5rem,4vw,2rem);font-weight:900;margin-bottom:1rem">
        <?php if ($query ?? ''): ?>
          Search results for <span class="gradient-text">"<?= View::e($query) ?>"</span>
        <?php else: ?>
          Search <span class="gradient-text">Events</span>
        <?php endif; ?>
      </h1>
      <form method="GET" action="<?= $appUrl ?>/search" style="max-width:640px">
        <div class="ne-search-bar" style="border-radius:var(--ne-radius)">
          <input type="text" name="q" value="<?= View::e($query ?? '') ?>"
                 placeholder="Search by title, organizer, or keyword…"
                 class="ne-search-input" autofocus autocomplete="off">
          <button type="submit" class="ne-search-btn" style="border-radius:0 var(--ne-radius) var(--ne-radius) 0">
            <i class="fas fa-search"></i>
          </button>
        </div>
      </form>
    </div>
  </div>

  <section class="ne-section">
    <div class="container-xl">
      <?php if (!($query ?? '')): ?>
        <div style="text-align:center;padding:4rem 2rem">
          <div class="ne-empty-icon" aria-hidden="true"><span></span><i class="fas fa-magnifying-glass"></i></div>
          <h3 style="font-weight:700">Search for events</h3>
          <p style="color:var(--ne-text-muted)">Type a keyword above to find events across India.</p>
        </div>
      <?php elseif (empty($events)): ?>
        <div style="text-align:center;padding:4rem 2rem;background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius-lg)">
          <div class="ne-empty-icon" aria-hidden="true"><span></span><i class="fas fa-magnifying-glass"></i></div>
          <h3 style="font-weight:700">No results for "<?= View::e($query) ?>"</h3>
          <p style="color:var(--ne-text-muted)">Try a different keyword, or browse all events.</p>
          <a href="<?= $appUrl ?>/discover" class="btn-ne btn-ne-primary mt-2">Browse All Events</a>
        </div>
      <?php else: ?>
        <div class="ne-section-header" style="margin-bottom:1.5rem">
          <p style="color:var(--ne-text-muted);margin:0"><?= number_format($total ?? 0) ?> results</p>
        </div>
        <?php $grid_events = $events; $grid_saved = false; include __DIR__ . '/../partials/event-grid.php'; ?>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
