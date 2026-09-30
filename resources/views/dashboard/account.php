<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$u      = $user ?? [];
ob_start();
?>
<?= View::partial('page-header', ['title' => 'Account <span class="gradient-text">Settings</span>', 'subtitle' => 'Your name, contact number and sign-in', 'width' => '640px']) ?>
<div class="band-cream ne-page-body">
  <div class="container-xl" style="max-width:640px">

    <?php if (!empty($_SESSION['flash_success'])): ?>
      <div class="ne-alert ne-alert-success mb-3">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['flash_success']) ?>
      </div>
      <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php $errors = $errors ?? []; ?>
    <form method="POST" action="<?= $appUrl ?>/account" class="ne-card" style="padding:1.5rem;margin-bottom:1.25rem" novalidate>
      <?= View::csrf() ?>
      <h2 style="font-size:1rem;font-weight:800;margin-bottom:1rem">Profile</h2>
      <div class="ne-form-group">
        <label class="ne-form-label" for="acc-name">Name</label>
        <input id="acc-name" name="name" type="text" maxlength="100" required autocomplete="name"
               class="ne-form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>" value="<?= View::e($u['name'] ?? '') ?>">
        <?php if (isset($errors['name'])): ?><div class="ne-form-error"><i class="fas fa-exclamation-circle fa-xs"></i><?= View::e($errors['name']) ?></div><?php endif; ?>
      </div>
      <div class="ne-form-group">
        <label class="ne-form-label" for="acc-phone">WhatsApp / mobile number</label>
        <input id="acc-phone" name="whatsapp_number" type="tel" inputmode="tel" autocomplete="tel" required
               class="ne-form-control<?= isset($errors['whatsapp_number']) ? ' is-invalid' : '' ?>" value="<?= View::e($u['whatsapp_number'] ?? '') ?>" placeholder="98765 43210">
        <?php if (isset($errors['whatsapp_number'])): ?><div class="ne-form-error"><i class="fas fa-exclamation-circle fa-xs"></i><?= View::e($errors['whatsapp_number']) ?></div><?php endif; ?>
        <div class="ne-form-hint">Kept on your account only — N Events never sends WhatsApp messages.</div>
      </div>
      <div class="ne-form-group">
        <div class="ne-form-label">Email</div>
        <div style="font-weight:600;overflow-wrap:anywhere"><?= View::e($u['email'] ?? '') ?>
          <?php if (!empty($u['email_verified_at'])): ?><span class="badge-ne badge-verified ms-1"><i class="fas fa-check-circle fa-xs"></i> Verified</span><?php endif; ?>
        </div>
        <div class="ne-form-hint">Your sign-in email can't be changed here.</div>
      </div>
      <div class="ne-form-actions app-save-bar">
        <a href="<?= $appUrl ?>/forgot-password" class="btn-ne btn-ne-ghost"><i class="fas fa-key fa-xs"></i> Change password</a>
        <button type="submit" class="btn-ne btn-ne-primary">Save changes</button>
      </div>
      <?php if (!empty($u['created_at'])): ?>
        <div class="ne-form-hint" style="margin-top:1rem">Member since <?= date('j F Y', strtotime($u['created_at'])) ?></div>
      <?php endif; ?>
    </form>

    <div style="background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius);padding:1.5rem">
      <h2 style="font-size:1rem;font-weight:700;margin-bottom:.75rem;color:var(--ne-accent)">Danger Zone</h2>
      <p style="font-size:.875rem;color:var(--ne-text-muted);margin-bottom:1rem">
        To delete your account or request a data export, contact us at privacy@nevents.in.
      </p>
      <a href="mailto:privacy@nevents.in?subject=Account%20Deletion%20Request" class="btn-ne btn-ne-ghost btn-ne-sm">
        <i class="fas fa-envelope fa-xs"></i> Contact Privacy Team
      </a>
    </div>

  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
