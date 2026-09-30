<?php
use NEvents\Core\View;
$appUrl    = rtrim($_ENV['APP_URL'] ?? '', '/');
$published = ($event['status'] ?? '') === 'published';
ob_start();
?>
<div class="<?= \NEvents\Helpers\AppMode::active() ? 'band-cream' : 'band-hero' ?> ne-submitted" style="min-height:80vh;padding:4rem 0">
  <div class="container-xl" style="max-width:640px">
    <div class="ne-card" style="padding:2.5rem;text-align:center">
      <div style="font-size:3rem;margin-bottom:1rem;color:<?= $published ? 'var(--ne-green)' : 'var(--ne-gold)' ?>">
        <i class="fas <?= $published ? 'fa-circle-check' : 'fa-hourglass-half' ?>"></i>
      </div>
      <h1 style="font-size:1.6rem;font-weight:900;margin-bottom:.75rem">
        <?= $published ? 'Your event has been published.' : 'Your event was received.' ?>
      </h1>
      <p style="color:var(--ne-text-muted);margin-bottom:.5rem">
        <strong style="color:var(--ne-text)"><?= View::e($event['title']) ?></strong>
      </p>
      <p style="color:var(--ne-text-muted);margin-bottom:2rem">
        <?php if ($published): ?>
          It now appears in event lists for its district and category, and anyone can save it or add it to their calendar.
        <?php else: ?>
          It's being held for a quick review before it appears in listings. <?= View::e($reason ?? '') ?>
          We'll email you when that's done.
        <?php endif; ?>
      </p>
      <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap">
        <?php if ($published): ?>
          <a href="<?= $appUrl ?>/event/<?= View::e($event['slug']) ?>" class="btn-ne btn-ne-primary"><i class="fas fa-eye fa-xs"></i> View Event</a>
        <?php endif; ?>
        <a href="<?= $appUrl ?>/my-events" class="btn-ne btn-ne-ghost"><i class="fas fa-list fa-xs"></i> My Events</a>
        <a href="<?= $appUrl ?>/events/create" class="btn-ne btn-ne-ghost"><i class="fas fa-plus fa-xs"></i> Post Another Event</a>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
