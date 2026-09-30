<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$email  = $email ?? '';

$maskedEmail = $email;
$at = strpos($email, '@');
if ($at !== false && $at > 0) {
    $local  = substr($email, 0, $at);
    $domain = substr($email, $at);
    $visible = mb_substr($local, 0, min(2, mb_strlen($local)));
    $maskedEmail = $visible . str_repeat('*', max(1, mb_strlen($local) - mb_strlen($visible))) . $domain;
}

ob_start();
?>
<div class="ne-auth-wrapper <?= \NEvents\Helpers\AppMode::active() ? 'band-cream' : 'band-hero' ?>">
  <?= View::partial('auth-aside') ?>
  <div class="ne-auth-card text-center">
    <div class="ne-logo-mobile"><?= View::partial('brand-logo', ['logo_height' => 40]) ?></div>
    <div style="width:72px;height:72px;border-radius:50%;background:rgba(var(--brand-accent-rgb),.1);border:2px solid rgba(var(--brand-accent-rgb),.4);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:2rem;color:var(--ne-gold)">
      <i class="fas fa-envelope-circle-check"></i>
    </div>

    <h1 style="font-size:1.4rem;font-weight:800;color:var(--ne-text)">Verify Your Email to Continue</h1>
    <p style="color:var(--ne-text-muted);margin-top:.5rem;line-height:1.6">
      Your password is correct, but <strong style="color:var(--ne-text)"><?= View::e($maskedEmail) ?></strong>
      hasn't been verified yet. Check your inbox (and spam folder) for the verification link we sent when you signed up.
    </p>

    <form method="POST" action="<?= $appUrl ?>/resend-verification" class="mt-3">
      <?= View::csrf() ?>
      <input type="hidden" name="email" value="<?= View::e($email) ?>">
      <button type="submit" class="btn-ne btn-ne-primary w-100 justify-content-center" style="padding:.85rem">
        <i class="fas fa-paper-plane fa-xs"></i> Resend Verification Email
      </button>
    </form>

    <div class="mt-3" style="display:flex;gap:.75rem;justify-content:center;font-size:.875rem">
      <a href="<?= $appUrl ?>/login" style="color:var(--ne-text-muted)">Back to Sign In</a>
      <span style="color:var(--ne-border)">·</span>
      <a href="<?= $appUrl ?>/" style="color:var(--ne-text-muted)">Home</a>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
