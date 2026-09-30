<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
ob_start();
?>
<section class="ne-section">
  <div class="container-xl" style="max-width:640px">
    <h1 class="ne-section-title animate-on-scroll">Submit Your <span class="gradient-text">Event</span></h1>
    <p style="color:var(--ne-text-muted);margin-bottom:2rem;line-height:1.7">
      List your event on N Events for free and reach thousands of people looking for events across India.
      We review submissions within 24 hours.
    </p>

    <?php if (!empty($_SESSION['flash_success'])): ?>
      <div class="ne-alert ne-alert-success mb-4">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($_SESSION['flash_success']) ?>
      </div>
      <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (empty($_SESSION['user_id'])): ?>
      <div class="ne-alert ne-alert-info mb-4">
        <i class="fas fa-info-circle"></i>
        <span>Please <a href="<?= $appUrl ?>/register" style="color:var(--ne-primary)">create an account</a> or <a href="<?= $appUrl ?>/login" style="color:var(--ne-primary)">log in</a> to submit an event. This helps us verify organizers and prevent spam.</span>
      </div>
      <div style="display:flex;gap:.75rem">
        <a href="<?= $appUrl ?>/register" class="btn-ne btn-ne-primary">Register</a>
        <a href="<?= $appUrl ?>/login" class="btn-ne btn-ne-secondary">Log In</a>
      </div>
    <?php else: ?>
      <div class="ne-alert ne-alert-info mb-4">
        <i class="fas fa-info-circle"></i>
        <span>For full organizer features, set up your <a href="<?= $appUrl ?>/organizer-portal" style="color:var(--ne-primary)">Organizer Profile</a>.</span>
      </div>
      <a href="<?= $appUrl ?>/organizer-portal/create-event" class="btn-ne btn-ne-primary">
        <i class="fas fa-plus fa-xs"></i> Submit via Organizer Portal
      </a>
    <?php endif; ?>
  </div>
</section>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
