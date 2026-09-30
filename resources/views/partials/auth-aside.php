<?php
/**
 * Brand panel shown beside every authentication form (hidden < 992px, where
 * the form card shows the compact logo instead). Optional: $heading, $text.
 */
use NEvents\Core\View;
?>
<aside class="ne-auth-aside" aria-label="About N Events">
  <?= View::partial('brand-logo', ['logo_variant' => 'full', 'logo_height' => 132]) ?>
  <div>
    <h2><?= View::e($heading ?? 'Stop searching for events.') ?><br><span class="gradient-text">Let the right events find you.</span></h2>
  </div>
  <p><?= View::e($text ?? 'Meetups, workshops, startup nights, rides and cultural events across India — in one place.') ?></p>
  <ul class="ne-auth-points">
    <li><i class="fas fa-location-dot"></i> Events in your district, matched to your interests</li>
    <li><i class="fas fa-envelope-open-text"></i> Email digests and reminders for events you save</li>
    <li><i class="fas fa-calendar-plus"></i> One-click Google Calendar, and post your own events free</li>
  </ul>
</aside>
