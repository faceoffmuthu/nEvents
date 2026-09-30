<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$errors = $errors ?? [];
ob_start();
?>
<div class="ne-auth-wrapper <?= \NEvents\Helpers\AppMode::active() ? 'band-cream' : 'band-hero' ?>">
  <?= View::partial('auth-aside') ?>
  <div class="ne-auth-card">
    <div class="text-center mb-4">
      <div class="ne-logo-mobile"><?= View::partial('brand-logo', ['logo_height' => 40]) ?></div>
      <h1 style="font-size:1.35rem;font-weight:800;margin:.75rem 0 .25rem">Set New Password</h1>
    </div>

    <?php if (!empty($error)): ?>
      <div class="ne-alert ne-alert-error mb-3"><i class="fas fa-exclamation-circle"></i><?= View::e($error) ?></div>
    <?php endif; ?>

    <form data-loading method="POST" action="<?= $appUrl ?>/reset-password">
      <?= View::csrf() ?>
      <input type="hidden" name="token" value="<?= View::e($token ?? '') ?>">

      <div class="ne-form-group">
        <label class="ne-form-label">New Password</label>
        <input type="password" name="password" class="ne-form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
               placeholder="Min 8 characters with a number" required>
        <?php if (isset($errors['password'])): ?>
          <div class="ne-form-error"><?= View::e($errors['password']) ?></div>
        <?php endif; ?>
      </div>

      <button type="submit" class="btn-ne btn-ne-primary w-100 justify-content-center" style="padding:1rem">
        <i class="fas fa-lock"></i> Update Password
      </button>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
