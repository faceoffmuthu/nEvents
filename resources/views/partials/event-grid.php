<?php
/**
 * A page's list of events: the website's card grid, or inside the app
 * (NEvents\Helpers\AppMode) a native list of rows (partials/event-row.php).
 *   $grid_events  events
 *   $grid_saved   true when every event is known to be saved (Saved events page)
 * Included with `include` (shares the caller's scope, like event-card.php).
 */
if (\NEvents\Helpers\AppMode::active()): ?>
  <div class="app-list">
    <?php foreach ($grid_events as $event): $is_saved = !empty($grid_saved); ?>
      <?php include __DIR__ . '/event-row.php'; ?>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="row g-4">
    <?php foreach ($grid_events as $event): $is_saved = !empty($grid_saved); ?>
      <div class="col-sm-6 col-xl-3 animate-on-scroll">
        <?php include __DIR__ . '/event-card.php'; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
