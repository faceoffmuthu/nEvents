<?php
use NEvents\Core\View;
$e      = $event;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$nextStart = $e['next_start'] ?? null;
$dateStr = '';
if ($nextStart) {
    $dt      = new DateTimeImmutable($nextStart, new DateTimeZone('UTC'));
    $dtLocal = $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
    $dateStr = $dtLocal->format('d M, h:i A');
}
?>
<a href="<?= $appUrl ?>/event/<?= View::e($e['slug']) ?>"
   class="ne-card d-flex align-items-center gap-3 p-3 text-decoration-none" style="--bs-card-border-radius:var(--ne-radius)">
  <div style="width:52px;height:52px;border-radius:12px;background:var(--ne-grad-primary);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.25rem;flex-shrink:0;opacity:0.85">
    <i class="fas fa-calendar-star"></i>
  </div>
  <div style="min-width:0;flex:1">
    <div style="font-weight:700;font-size:.875rem;color:var(--ne-text);line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
      <?= View::e($e['title']) ?>
    </div>
    <div style="font-size:.75rem;color:var(--ne-text-muted);margin-top:.2rem;display:flex;align-items:center;gap:.5rem">
      <?php if ($dateStr): ?>
        <span><i class="fas fa-clock fa-xs" style="color:var(--ne-secondary)"></i> <?= View::e($dateStr) ?></span>
      <?php endif; ?>
      <?php if (($e['pricing_type'] ?? '') === 'free'): ?>
        <span class="badge-ne badge-free" style="font-size:.65rem">FREE</span>
      <?php endif; ?>
    </div>
  </div>
  <i class="fas fa-chevron-right fa-xs" style="color:var(--ne-text-dim);flex-shrink:0"></i>
</a>
