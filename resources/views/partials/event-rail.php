<?php
use NEvents\Core\View;
/**
 * A titled row of event cards: swipeable on phones/tablets, a 4-column grid on desktop.
 *   $rail_title    heading text
 *   $rail_icon     Font Awesome icon (optional)
 *   $rail_sub      subtitle (optional)
 *   $rail_link     "See all" URL (optional)
 *   $rail_events   events (first 8 shown on phones, 4 on desktop)
 * Cards are never scroll-animated here: sideways items may never enter the viewport vertically.
 */
$__rail = array_slice($rail_events ?? [], 0, 8);
if (!$__rail) return;
?>
<div class="ne-rail-block">
  <div class="ne-section-header">
    <div>
      <h2 class="ne-section-title"><?php if (!empty($rail_icon)): ?><i class="fas <?= View::e($rail_icon) ?> ne-rail-icon" aria-hidden="true"></i><?php endif; ?><?= View::e($rail_title) ?></h2>
      <?php if (!empty($rail_sub)): ?><p class="ne-section-subtitle"><?= View::e($rail_sub) ?></p><?php endif; ?>
    </div>
    <?php if (!empty($rail_link)): ?>
      <a href="<?= View::e($rail_link) ?>" class="ne-section-link">See all <i class="fas fa-arrow-right fa-xs" aria-hidden="true"></i></a>
    <?php endif; ?>
  </div>
  <div class="ne-rail">
    <?php foreach ($__rail as $__i => $event): ?>
      <div class="<?= $__i >= 4 ? 'd-lg-none' : '' ?>"><?php include __DIR__ . '/event-card.php'; ?></div>
    <?php endforeach; ?>
  </div>
</div>
