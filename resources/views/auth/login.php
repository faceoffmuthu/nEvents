<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$error  = $error ?? null;
$old    = $old   ?? [];

ob_start();
?>
<div class="ne-auth-wrapper <?= \NEvents\Helpers\AppMode::active() ? 'band-cream' : 'band-hero' ?>">
  <div style="position:fixed;inset:0;background:radial-gradient(ellipse at 70% 30%,rgba(var(--brand-primary-rgb),.1) 0%,transparent 60%),radial-gradient(ellipse at 30% 80%,rgba(var(--brand-primary-rgb),.1) 0%,transparent 50%);pointer-events:none"></div>

  <?= View::partial('auth-aside') ?>

  <div class="ne-auth-card">
    <div class="text-center mb-4">
      <div class="ne-logo-mobile"><?= View::partial('brand-logo', ['logo_height' => 40]) ?></div>
      <h1 style="font-size:1.35rem;font-weight:800;margin:.75rem 0 .25rem;color:var(--ne-text)">Welcome back</h1>
      <p style="font-size:.875rem;color:var(--ne-text-muted);margin:0">Sign in to your personalized event feed.</p>
    </div>

    <?php if ($error): ?>
      <div class="ne-alert ne-alert-error mb-3">
        <i class="fas fa-exclamation-circle"></i>
        <?= View::e($error) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_success'])): ?>
      <div class="ne-alert ne-alert-success mb-3">
        <i class="fas fa-check-circle"></i>
        <?= View::e($_SESSION['flash_success']) ?>
      </div>
      <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <form data-loading method="POST" action="<?= $appUrl ?>/login" class="ne-form-validated" novalidate>
      <?= View::csrf() ?>

      <div class="ne-form-group">
        <label class="ne-form-label">Email Address</label>
        <input type="email" name="email" class="ne-form-control"
               placeholder="you@example.com"
               value="<?= View::e($old['email'] ?? '') ?>"
               autocomplete="email" required>
      </div>

      <div class="ne-form-group">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
          <label class="ne-form-label" style="margin:0">Password</label>
          <a href="<?= $appUrl ?>/forgot-password" style="font-size:.78rem;color:var(--ne-primary)">Forgot password?</a>
        </div>
        <div style="position:relative">
          <input type="password" name="password" id="login-password" class="ne-form-control"
                 placeholder="Your password" autocomplete="current-password" required>
          <button type="button" onclick="togglePwd('login-password','eye-login')"
                  style="position:absolute;right:1rem;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--ne-text-dim);cursor:pointer">
            <i id="eye-login" class="fas fa-eye fa-xs"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-ne btn-ne-primary w-100 justify-content-center" style="font-size:1rem;padding:1rem;margin-top:.5rem">
        <i class="fas fa-sign-in-alt"></i> Sign In
      </button>
    </form>

    <div class="text-center mt-3" style="font-size:.875rem;color:var(--ne-text-muted)">
      Don't have an account?
      <a href="<?= $appUrl ?>/register" style="color:var(--ne-primary);font-weight:600">Create one free</a>
    </div>
    <div class="text-center mt-2" style="font-size:.8rem;color:var(--ne-text-dim)">
      Didn't get a verification email? <a href="<?= $appUrl ?>/resend-verification" style="color:var(--ne-text-muted)">Resend it</a>
    </div>
    <?php if (\NEvents\Helpers\AppMode::active()): /* the app has no website footer */ ?>
      <nav class="app-auth-links" aria-label="About N Events">
        <a href="<?= $appUrl ?>/about">About</a><a href="<?= $appUrl ?>/faq">Help</a><a href="<?= $appUrl ?>/privacy">Privacy</a><a href="<?= $appUrl ?>/terms">Terms</a>
      </nav>
    <?php endif; ?>
  </div>
</div>
<script>
function togglePwd(inputId, iconId) {
  const inp  = document.getElementById(inputId);
  const icon = document.getElementById(iconId);
  inp.type   = inp.type === 'password' ? 'text' : 'password';
  icon.className = inp.type === 'text' ? 'fas fa-eye-slash fa-xs' : 'fas fa-eye fa-xs';
}
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
