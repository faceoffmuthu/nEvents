<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
ob_start();
?>
<?= View::partial('page-header', ['title' => 'Frequently Asked <span class="gradient-text">Questions</span>', 'center' => true, 'width' => '800px']) ?>
<section class="ne-section band-cream">
  <div class="container-xl" style="max-width:800px">

    <?php
    $faqs = [
        ['Is N Events free to use?', 'Yes — discovering and saving events is completely free. There is no subscription required.'],
        ['How does N Events source events?', 'We aggregate events from multiple public sources including organiser websites, RSS feeds, and structured data. We do not scrape private data or bypass authentication.'],
        ['Can I buy tickets on N Events?', 'No — N Events is a discovery platform, not a ticketing system. The "Register" button takes you directly to the organiser\'s own registration page.'],
        ['How do I get my event listed?', 'Submit your event through the "Submit an Event" form. Our team reviews and publishes events within 24 hours.'],
        ['How do I stop receiving notifications?', 'Go to Dashboard → Notification Preferences to opt out of any or all notification channels at any time.'],
        ['Are events verified?', 'Yes — every event goes through an automated verification process and a trust scoring system. Verified events are marked with a badge.'],
    ];
    foreach ($faqs as $i => [$q, $a]):
    ?>
      <div style="background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius);margin-bottom:.75rem;overflow:hidden">
        <details>
          <summary style="padding:1.25rem;font-weight:600;cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center;color:var(--ne-text)">
            <?= View::e($q) ?>
            <i class="fas fa-chevron-down fa-xs" style="color:var(--ne-primary);flex-shrink:0"></i>
          </summary>
          <div style="padding:0 1.25rem 1.25rem;color:var(--ne-text-muted);line-height:1.7;border-top:1px solid var(--ne-border)"><?= View::e($a) ?></div>
        </details>
      </div>
    <?php endforeach; ?>

    <div style="text-align:center;margin-top:2.5rem">
      <p style="color:var(--ne-text-muted);margin-bottom:1rem">Still have questions?</p>
      <a href="<?= $appUrl ?>/contact" class="btn-ne btn-ne-primary">Contact Us</a>
    </div>
  </div>
</section>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
