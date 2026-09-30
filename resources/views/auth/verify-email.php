<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
ob_start();
?>
<div class="ne-auth-wrapper <?= \NEvents\Helpers\AppMode::active() ? 'band-cream' : 'band-hero' ?>">
  <?= View::partial('auth-aside') ?>
  <div class="ne-auth-card text-center">
    <div class="ne-logo-mobile"><?= View::partial('brand-logo', ['logo_height' => 40]) ?></div>
    <div class="mb-4">
      <?php if (empty($error)): ?>
        <div style="width:72px;height:72px;border-radius:50%;background:rgba(var(--success-rgb),.1);border:2px solid rgba(var(--success-rgb),.4);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:2rem;color:var(--ne-green)">
          <i class="fas fa-check-circle"></i>
        </div>
        <h1 style="font-size:1.5rem;font-weight:800;color:var(--ne-text)">Email Verified!</h1>
        <p style="color:var(--ne-text-muted);margin-top:.5rem">Your account is now active. Sign in to discover events.</p>
        <a href="<?= $appUrl ?>/login" class="btn-ne btn-ne-primary mt-3">Sign In Now</a>
      <?php elseif (!empty($already_verified)): ?>
        <div style="width:72px;height:72px;border-radius:50%;background:rgba(var(--success-rgb),.1);border:2px solid rgba(var(--success-rgb),.4);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:2rem;color:var(--ne-green)">
          <i class="fas fa-check-circle"></i>
        </div>
        <h1 style="font-size:1.5rem;font-weight:800;color:var(--ne-text)">Already Verified</h1>
        <p style="color:var(--ne-text-muted);margin-top:.5rem">Your email address has already been verified.</p>
        <a href="<?= $appUrl ?>/login" class="btn-ne btn-ne-primary mt-3">Sign In</a>
      <?php else: ?>
        <div style="width:72px;height:72px;border-radius:50%;background:rgba(var(--danger-rgb),.1);border:2px solid rgba(var(--danger-rgb),.4);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:2rem;color:var(--ne-accent)">
          <i class="fas fa-times-circle"></i>
        </div>
        <h1 style="font-size:1.5rem;font-weight:800;color:var(--ne-text)">Verification Failed</h1>
        <p style="color:var(--ne-text-muted);margin-top:.5rem"><?= View::e($error) ?></p>
        <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;margin-top:1rem">
          <a href="<?= $appUrl ?>/resend-verification" class="btn-ne btn-ne-primary">Resend Verification Email</a>
          <a href="<?= $appUrl ?>/register" class="btn-ne btn-ne-ghost">Create New Account</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
