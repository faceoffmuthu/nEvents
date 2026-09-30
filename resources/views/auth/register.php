<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$errors = $errors ?? [];
$old    = $old    ?? [];

ob_start();
?>
<div class="ne-auth-wrapper <?= \NEvents\Helpers\AppMode::active() ? 'band-cream' : 'band-hero' ?>">
  <!-- Background -->
  <div style="position:fixed;inset:0;background:radial-gradient(ellipse at 30% 50%,rgba(var(--brand-primary-rgb),.12) 0%,transparent 60%),radial-gradient(ellipse at 70% 80%,rgba(var(--brand-primary-rgb),.08) 0%,transparent 50%);pointer-events:none"></div>

  <?= View::partial('auth-aside') ?>

  <div class="ne-auth-card">
    <!-- Logo -->
    <div class="text-center mb-4">
      <div class="ne-logo-mobile"><?= View::partial('brand-logo', ['logo_height' => 40]) ?></div>
      <h1 style="font-size:1.35rem;font-weight:800;margin:.75rem 0 .25rem;color:var(--ne-text)">Create your account</h1>
      <p style="font-size:.875rem;color:var(--ne-text-muted);margin:0">Discover events personalized for you.</p>
    </div>

    <?php if (!empty($errors['_general'])): ?>
      <div class="ne-alert ne-alert-error mb-3">
        <i class="fas fa-exclamation-circle"></i>
        <?= View::e($errors['_general']) ?>
      </div>
    <?php endif; ?>

    <form data-loading method="POST" action="<?= $appUrl ?>/register" class="ne-form-validated" novalidate>
      <?= View::csrf() ?>

      <div class="ne-form-group">
        <label class="ne-form-label">Full Name</label>
        <input type="text" name="name" class="ne-form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
               placeholder="Your full name" value="<?= View::e($old['name'] ?? '') ?>" required>
        <?php if (isset($errors['name'])): ?>
          <div class="ne-form-error"><i class="fas fa-exclamation-circle fa-xs"></i><?= View::e($errors['name']) ?></div>
        <?php endif; ?>
      </div>

      <div class="ne-form-group">
        <label class="ne-form-label">Email Address</label>
        <input type="email" name="email" class="ne-form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
               placeholder="you@example.com" value="<?= View::e($old['email'] ?? '') ?>" required>
        <?php if (isset($errors['email'])): ?>
          <div class="ne-form-error"><i class="fas fa-exclamation-circle fa-xs"></i><?= View::e($errors['email']) ?></div>
        <?php endif; ?>
      </div>

      <div class="ne-form-group">
        <label class="ne-form-label">WhatsApp / Mobile Number</label>
        <input type="tel" name="whatsapp_number" autocomplete="tel"
               class="ne-form-control <?= isset($errors['whatsapp_number']) ? 'is-invalid' : '' ?>"
               placeholder="98765 43210 or +44 20 7946 0958" value="<?= View::e($old['whatsapp_number'] ?? '') ?>" required>
        <div class="ne-form-hint">Indian numbers can be entered without +91. Stored with your account only — we don't send WhatsApp messages.</div>
        <?php if (isset($errors['whatsapp_number'])): ?>
          <div class="ne-form-error"><i class="fas fa-exclamation-circle fa-xs"></i><?= View::e($errors['whatsapp_number']) ?></div>
        <?php endif; ?>
      </div>

      <div class="ne-form-group">
        <label class="ne-form-label">Password</label>
        <div style="position:relative">
          <input type="password" name="password" id="password"
                 class="ne-form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                 placeholder="Min 8 chars with a number" required>
          <button type="button" onclick="togglePwd('password','eye1')"
                  style="position:absolute;right:1rem;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--ne-text-dim);cursor:pointer">
            <i id="eye1" class="fas fa-eye fa-xs"></i>
          </button>
        </div>
        <?php if (isset($errors['password'])): ?>
          <div class="ne-form-error"><i class="fas fa-exclamation-circle fa-xs"></i><?= View::e($errors['password']) ?></div>
        <?php endif; ?>
      </div>

      <div class="ne-form-group">
        <label class="ne-form-label">Confirm Password</label>
        <input type="password" name="password_confirm" id="password_confirm"
               class="ne-form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>"
               placeholder="Repeat your password" required>
        <?php if (isset($errors['password_confirm'])): ?>
          <div class="ne-form-error"><i class="fas fa-exclamation-circle fa-xs"></i><?= View::e($errors['password_confirm']) ?></div>
        <?php endif; ?>
      </div>

      <!-- Consent -->
      <div style="background:var(--ne-bg-glass);border:1px solid var(--ne-border);border-radius:var(--ne-radius-sm);padding:1rem;margin-bottom:1.25rem">
        <label style="display:flex;align-items:flex-start;gap:.75rem;cursor:pointer;font-size:.8rem;color:var(--ne-text-muted)">
          <input type="checkbox" name="accept_terms" required style="margin-top:2px;accent-color:var(--ne-primary);flex-shrink:0">
          <span>I agree to the <a href="<?= $appUrl ?>/terms" target="_blank" style="color:var(--ne-primary)">Terms &amp; Conditions</a>
          and <a href="<?= $appUrl ?>/privacy" target="_blank" style="color:var(--ne-primary)">Privacy Policy</a>.
          I confirm I am 18 years or older.</span>
        </label>
        <?php if (isset($errors['accept_terms'])): ?>
          <div class="ne-form-error"><i class="fas fa-exclamation-circle fa-xs"></i><?= View::e($errors['accept_terms']) ?></div>
        <?php endif; ?>
      </div>

      <button type="submit" class="btn-ne btn-ne-primary w-100 justify-content-center" style="font-size:1rem;padding:1rem">
        <i class="fas fa-user-plus"></i> Create Account
      </button>
    </form>

    <div class="text-center mt-3" style="font-size:.875rem;color:var(--ne-text-muted)">
      Already have an account?
      <a href="<?= $appUrl ?>/login" style="color:var(--ne-primary);font-weight:600">Sign In</a>
    </div>
  </div>
</div>

<script>
function togglePwd(inputId, iconId) {
  const inp  = document.getElementById(inputId);
  const icon = document.getElementById(iconId);
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.className = 'fas fa-eye-slash fa-xs';
  } else {
    inp.type = 'password';
    icon.className = 'fas fa-eye fa-xs';
  }
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
