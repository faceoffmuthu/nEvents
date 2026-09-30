<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
ob_start();
?>
<div class="ne-auth-wrapper <?= \NEvents\Helpers\AppMode::active() ? 'band-cream' : 'band-hero' ?>" style="flex-direction:column;gap:2rem;align-items:center">
  <div class="ne-auth-card" style="max-width:540px">
    <div style="text-align:center;margin-bottom:1.5rem">
      <div style="font-size:2.5rem;margin-bottom:.75rem">🏢</div>
      <h1 style="font-size:1.5rem;font-weight:800;margin-bottom:.5rem">Set Up Your Organizer Profile</h1>
      <p style="color:var(--ne-text-muted);font-size:.9rem">Create your organizer profile to start submitting and managing events.</p>
    </div>

    <div class="ne-alert ne-alert-info mb-4">
      <i class="fas fa-info-circle"></i>
      <span>Organizer portal is in early access. Please email <strong>organizers@nevents.in</strong> to get set up — we'll activate your profile within 24 hours.</span>
    </div>

    <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap">
      <a href="mailto:organizers@nevents.in" class="btn-ne btn-ne-primary">
        <i class="fas fa-envelope fa-xs"></i> Email Us
      </a>
      <a href="<?= $appUrl ?>/dashboard" class="btn-ne btn-ne-ghost">Back to Dashboard</a>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
