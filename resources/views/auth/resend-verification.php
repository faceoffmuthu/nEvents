<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$email  = $email ?? '';
ob_start();
?>
<div class="ne-auth-wrapper <?= \NEvents\Helpers\AppMode::active() ? 'band-cream' : 'band-hero' ?>">
  <?= View::partial('auth-aside') ?>
  <div class="ne-auth-card">
    <div class="text-center mb-4">
      <div class="ne-logo-mobile"><?= View::partial('brand-logo', ['logo_height' => 40]) ?></div>
      <h1 style="font-size:1.35rem;font-weight:800;margin:.75rem 0 .25rem;color:var(--ne-text)">Resend Verification Email</h1>
      <p style="font-size:.875rem;color:var(--ne-text-muted);margin:0">
        Enter the email you signed up with and we'll send a fresh verification link.
      </p>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?>
      <div class="ne-alert ne-alert-success mb-3">
        <i class="fas fa-check-circle"></i>
        <?= View::e($_SESSION['flash_success']) ?>
      </div>
      <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <form data-loading method="POST" action="<?= $appUrl ?>/resend-verification" class="ne-form-validated" novalidate>
      <?= View::csrf() ?>
      <div class="ne-form-group">
        <label class="ne-form-label">Email Address</label>
        <input type="email" name="email" class="ne-form-control"
               placeholder="you@example.com" value="<?= View::e($email) ?>"
               autocomplete="email" required>
      </div>
      <button type="submit" class="btn-ne btn-ne-primary w-100 justify-content-center" style="font-size:1rem;padding:1rem">
        <i class="fas fa-paper-plane fa-xs"></i> Send Verification Email
      </button>
    </form>

    <div class="text-center mt-3" style="font-size:.875rem;color:var(--ne-text-muted)">
      <a href="<?= $appUrl ?>/login" style="color:var(--ne-primary);font-weight:600">Back to Sign In</a>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
