<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
ob_start();
?>
<?= View::partial('page-header', ['title' => 'About <span class="gradient-text">N Events</span>', 'subtitle' => 'Event discovery across India', 'width' => '800px']) ?>
<section class="ne-section band-cream">
  <div class="container-xl" style="max-width:800px">

    <div style="color:var(--ne-text-muted);line-height:1.8;font-size:1rem">
      <p>N Events is India's personalized event discovery, aggregation, intelligence, recommendation and notification platform. We aggregate events from dozens of sources — meetups, conferences, workshops, concerts, festivals and more — so you never miss what matters to you.</p>

      <h2 style="font-size:1.25rem;font-weight:700;color:var(--ne-text);margin:2rem 0 .75rem">Our Mission</h2>
      <p>Stop searching for events. Let the right events find you. We use intelligent recommendation algorithms tuned to your interests, location, and past engagement — so your feed gets smarter every time you use it.</p>

      <h2 style="font-size:1.25rem;font-weight:700;color:var(--ne-text);margin:2rem 0 .75rem">Coverage</h2>
      <p>Events from across India — every state, union territory and district. We verify every event before it appears on the platform.</p>

      <h2 style="font-size:1.25rem;font-weight:700;color:var(--ne-text);margin:2rem 0 .75rem">For Organizers</h2>
      <p>N Events is not a ticketing system — we link directly to your own registration pages. Submit your event for free and reach thousands of relevant attendees.</p>

      <div style="margin-top:2.5rem;display:flex;gap:1rem;flex-wrap:wrap">
        <a href="<?= $appUrl ?>/discover" class="btn-ne btn-ne-primary">Discover Events</a>
        <a href="<?= $appUrl ?>/submit-event" class="btn-ne btn-ne-secondary">Submit Your Event</a>
        <a href="<?= $appUrl ?>/contact" class="btn-ne btn-ne-ghost">Contact Us</a>
      </div>
    </div>
  </div>
</section>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
