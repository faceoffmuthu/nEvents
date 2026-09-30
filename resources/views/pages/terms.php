<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
ob_start();
?>
<?= View::partial('page-header', ['title' => "Terms of <span class=\"gradient-text\">Service</span>", 'subtitle' => 'Last updated: ' . date('F Y'), 'width' => '800px']) ?>
<section class="ne-section band-cream">
  <div class="container-xl" style="max-width:800px">

    <?php
    $sections = [
        ['Acceptance', 'By using N Events you agree to these terms. If you do not agree, please do not use the platform.'],
        ['Platform Role', 'N Events is an event discovery and aggregation platform. We are not an event organiser and are not responsible for the content, quality, or cancellation of any listed event. Registration for events occurs on the organiser\'s own website.'],
        ['User Conduct', 'You agree not to submit false information, spam the platform, scrape data, or attempt to circumvent security controls.'],
        ['Content', 'Event information is sourced from public listings. If you are an organiser and wish to correct or remove your event listing, contact us at hello@nevents.in.'],
        ['Liability', 'N Events is provided "as is". We make no warranties regarding the accuracy of event information. Our liability is limited to the maximum extent permitted by Indian law.'],
        ['Governing Law', 'These terms are governed by the laws of India. Disputes shall be subject to the exclusive jurisdiction of courts in Chennai, Tamil Nadu.'],
    ];
    foreach ($sections as [$title, $body]):
    ?>
      <div style="margin-bottom:2rem">
        <h2 style="font-size:1.1rem;font-weight:700;color:var(--ne-text);margin-bottom:.75rem"><?= $title ?></h2>
        <p style="color:var(--ne-text-muted);line-height:1.8"><?= $body ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
