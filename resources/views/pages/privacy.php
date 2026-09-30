<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
ob_start();
?>
<?= View::partial('page-header', ['title' => "Privacy <span class=\"gradient-text\">Policy</span>", 'subtitle' => 'Last updated: ' . date('F Y'), 'width' => '800px']) ?>
<section class="ne-section band-cream">
  <div class="container-xl" style="max-width:800px">

    <?php
    $sections = [
        ['Data We Collect', 'We collect information you provide when registering (name, email, phone), your location and event preferences, and usage data such as which events you view or save. We do not store payment information.'],
        ['How We Use It', 'Your data is used to personalise your event feed, send notifications you have consented to, and improve the platform. We never sell your data to third parties.'],
        ['Notifications', 'Notifications are sent by email only, and you can opt out at any time from your dashboard preferences. The WhatsApp/mobile number you give at signup is stored with your account — we do not send WhatsApp messages. We maintain detailed consent records.'],
        ['Data Retention', 'You can request deletion of your account and personal data at any time by emailing privacy@nevents.in. We will process requests within 30 days.'],
        ['Cookies', 'We use strictly necessary session cookies only. No third-party tracking cookies are set without your consent.'],
        ['Contact', 'For privacy enquiries: privacy@nevents.in'],
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
