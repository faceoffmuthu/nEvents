<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$e = $event;
ob_start();
?>
<div class="ne-auth-wrapper <?= \NEvents\Helpers\AppMode::active() ? 'band-cream' : 'band-hero' ?>" style="text-align:center;flex-direction:column;gap:2rem">
  <div class="ne-auth-card" style="max-width:560px">
    <!-- Animated icon -->
    <div style="width:80px;height:80px;border-radius:50%;background:var(--ne-grad-primary);display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;font-size:2rem;color:#fff;animation:pulse-glow 1.5s infinite alternate">
      <i class="fas fa-external-link-alt"></i>
    </div>

    <h1 style="font-size:1.5rem;font-weight:800;margin-bottom:.75rem">Redirecting to Registration</h1>

    <div style="background:var(--ne-bg-glass);border:1px solid var(--ne-border);border-radius:var(--ne-radius-sm);padding:1.25rem;margin-bottom:1.5rem">
      <div style="font-size:.75rem;color:var(--ne-text-dim);text-transform:uppercase;letter-spacing:.1em;margin-bottom:.5rem">Event</div>
      <div style="font-weight:700;color:var(--ne-text)"><?= View::e($e['title']) ?></div>
      <?php if (!empty($e['organizer_name'])): ?>
        <div style="font-size:.8rem;color:var(--ne-text-muted);margin-top:.25rem">by <?= View::e($e['organizer_name']) ?></div>
      <?php endif; ?>
    </div>

    <div class="ne-alert ne-alert-info mb-3">
      <i class="fas fa-info-circle"></i>
      <span>You are being redirected to the organizer's website to complete registration.
      N Events does not process payments or store your registration data.</span>
    </div>

    <div style="font-size:.875rem;color:var(--ne-text-muted);margin-bottom:1.5rem">
      Redirecting in <strong style="color:var(--ne-primary);font-size:1.1rem"
        data-redirect-countdown
        data-url="<?= View::e($external_url) ?>"
        data-seconds="5">5</strong> seconds…
    </div>

    <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap">
      <a href="<?= View::e($external_url) ?>" target="_blank" rel="noopener noreferrer" class="btn-ne btn-ne-primary">
        <i class="fas fa-external-link-alt"></i> Go to Registration Now
      </a>
      <a href="<?= $appUrl ?>/event/<?= View::e($e['slug']) ?>" class="btn-ne btn-ne-ghost">
        <i class="fas fa-arrow-left fa-xs"></i> Back to Event
      </a>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
