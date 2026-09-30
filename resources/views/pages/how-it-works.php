<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
ob_start();
?>
<?= View::partial('page-header', ['title' => 'How <span class="gradient-text">N Events</span> Works', 'subtitle' => 'Stop searching. Let the right events find you.', 'center' => true, 'width' => '900px']) ?>
<section class="ne-section band-cream">
  <div class="container-xl" style="max-width:900px">

    <div class="row g-4 mt-2">
      <?php
      $steps = [
          ['01', '🤖', 'We Aggregate', 'Our platform collects events from dozens of sources — websites, RSS feeds, social media — automatically and continuously.', 'var(--ne-primary)'],
          ['02', '✅', 'We Verify',    'Every event is validated and trust-scored before appearing on the platform. Duplicate events are automatically merged.', 'var(--ne-secondary)'],
          ['03', '🎯', 'We Personalise', 'Tell us your interests and location. Our recommendation engine learns from your behaviour to surface the most relevant events.', 'var(--ne-green)'],
          ['04', '🔔', 'We Notify',    'Get email digests and reminders when events matching your preferences are added — and add them to Google Calendar in one click.', 'var(--ne-gold)'],
      ];
      foreach ($steps as [$num, $emoji, $title, $desc, $color]):
      ?>
        <div class="col-md-6 animate-on-scroll">
          <div style="background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius-lg);padding:2rem;height:100%;transition:border-color .25s"
               onmouseover="this.style.borderColor='<?= $color ?>'"
               onmouseout="this.style.borderColor='var(--ne-border)'">
            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem">
              <div style="width:48px;height:48px;border-radius:50%;background:<?= $color ?>;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;animation:pulse-glow 2s infinite alternate">
                <?= $emoji ?>
              </div>
              <div>
                <div style="font-size:.7rem;font-weight:700;color:<?= $color ?>;letter-spacing:.1em">STEP <?= $num ?></div>
                <div style="font-size:1.1rem;font-weight:700;color:var(--ne-text)"><?= $title ?></div>
              </div>
            </div>
            <p style="color:var(--ne-text-muted);line-height:1.7;margin:0"><?= $desc ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="text-align:center;margin-top:3rem">
      <a href="<?= $appUrl ?>/register" class="btn-ne btn-ne-primary">
        <i class="fas fa-rocket fa-xs"></i> Get Started Free
      </a>
    </div>
  </div>
</section>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
