<?php
use NEvents\Core\View;
/**
 * App top bar (app mode only, see NEvents\Helpers\AppMode): replaces the website navbar.
 *   $bar_title    screen title
 *   $bar_back     href of the screen "above" this one (a back arrow is shown; nevents.js
 *                 goes back in history when the previous screen was in the app), or null
 *   $bar_brand    show the N Events mark + name instead of a title (home)
 *   $bar_actions  optional HTML for icon buttons on the right (views pass $app_actions)
 */
?>
<header class="ne-appbar">
  <div class="ne-appbar-inner">
    <?php if (!empty($bar_back)): ?>
      <a href="<?= View::e($bar_back) ?>" class="ne-appbar-btn" data-app-back aria-label="Back"><i class="fas fa-arrow-left" aria-hidden="true"></i></a>
    <?php endif; ?>
    <?php if (!empty($bar_brand)): ?>
      <a href="<?= View::e(rtrim($_ENV['APP_URL'] ?? '', '/') . '/') ?>" class="ne-appbar-brand" aria-label="N Events home">
        <img src="<?= View::e(rtrim($_ENV['APP_URL'] ?? '', '/') . '/images/brand/n-events-mark.png') ?>" alt="" width="30" height="29">
        <span>N Events</span>
      </a>
    <?php else: ?>
      <div class="ne-appbar-title<?= empty($bar_back) ? ' is-root' : '' ?>"><?= View::e($bar_title ?? '') ?></div>
    <?php endif; ?>
    <div class="ne-appbar-actions"><?= $bar_actions ?? '' ?></div>
  </div>
  <div class="ne-appbar-progress" aria-hidden="true"></div>
</header>
