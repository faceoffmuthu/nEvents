<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$e = $event ?? [];
ob_start();
?>
<div class="ne-auth-wrapper <?= \NEvents\Helpers\AppMode::active() ? 'band-cream' : 'band-hero' ?>" style="flex-direction:column;gap:2rem;text-align:center">
  <div class="ne-auth-card" style="max-width:520px">
    <div style="width:72px;height:72px;border-radius:50%;background:rgba(var(--danger-rgb),.15);border:2px solid var(--ne-accent);display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto 1.5rem">
      🎫
    </div>
    <h1 style="font-size:1.5rem;font-weight:800;margin-bottom:.75rem">No Online Registration</h1>

    <div style="background:var(--ne-bg-glass);border:1px solid var(--ne-border);border-radius:var(--ne-radius-sm);padding:1.25rem;margin-bottom:1.5rem;text-align:left">
      <div style="font-weight:700;margin-bottom:.25rem"><?= View::e($e['title'] ?? '') ?></div>
      <?php if (!empty($e['organizer_name'])): ?>
        <div style="font-size:.8rem;color:var(--ne-text-muted)">by <?= View::e($e['organizer_name']) ?></div>
      <?php endif; ?>
    </div>

    <div class="ne-alert ne-alert-info mb-4">
      <i class="fas fa-info-circle"></i>
      <span>This event does not have an online registration link. Contact the organizer directly to register.</span>
    </div>

    <a href="<?= $appUrl ?>/event/<?= View::e($e['slug'] ?? '') ?>" class="btn-ne btn-ne-secondary">
      <i class="fas fa-arrow-left fa-xs"></i> Back to Event
    </a>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
