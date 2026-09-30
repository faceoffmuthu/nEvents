<?php
use NEvents\Core\View;
use NEvents\Services\Events\EventImageService;

/**
 * Event card — the one card used everywhere (grids, home rails, search).
 * The whole card is a single link (stretched title link); the heart saves
 * without leaving the page. Layout: image (price/format chips + heart),
 * date line, title, organizer, place, category · saves.
 * Expected: $event. Optional: $is_saved (otherwise looked up for the signed-in user).
 */
$e       = $event;
$appUrl  = rtrim($_ENV['APP_URL'] ?? '', '/');
$isSaved = !empty($is_saved)
    || \NEvents\Core\Application::getInstance()->get(\NEvents\Repositories\EventRepository::class)->isSavedByCurrentUser((int) $e['id']);
$image   = EventImageService::resolve($e);
$url     = $appUrl . '/event/' . $e['slug'];

$when = '';
if (!empty($e['next_start'])) {
    $local = (new DateTimeImmutable($e['next_start'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Kolkata'));
    $when  = strtoupper($local->format('D, j M')) . ' · ' . $local->format('g:i A');
}

$price = null;
if (($e['pricing_type'] ?? '') === 'free') {
    $price = 'Free';
} elseif (($e['pricing_type'] ?? '') === 'paid') {
    $price = !empty($e['min_price']) ? (($e['currency'] ?? 'INR') === 'INR' ? '₹' : $e['currency'] . ' ') . number_format((float) $e['min_price']) : 'Paid';
}

$isOnline = ($e['format'] ?? '') === 'online';
$place    = $isOnline ? 'Online' : trim(implode(', ', array_filter([
    $e['venue_name'] ?? null,
    ($e['city_name'] ?? null) ?: ($e['venue_locality'] ?? null),
])));
$saves = (int) ($e['save_count'] ?? 0);
?>
<article class="event-card">
  <div class="event-card-img">
    <img src="<?= View::e($image['url']) ?>" alt="<?= View::e($image['alt']) ?>" loading="lazy" decoding="async">
    <div class="event-card-badges">
      <?php if ($price !== null): ?>
        <span class="badge-ne <?= $price === 'Free' ? 'badge-free' : 'badge-price' ?>"><?= View::e($price) ?></span>
      <?php endif; ?>
      <?php if ($isOnline): ?>
        <span class="badge-ne badge-online"><i class="fas fa-laptop fa-xs"></i> Online</span>
      <?php elseif (($e['format'] ?? '') === 'hybrid'): ?>
        <span class="badge-ne badge-offline">Hybrid</span>
      <?php endif; ?>
      <?php if (!empty($e['is_featured'])): ?>
        <span class="badge-ne badge-featured"><i class="fas fa-star fa-xs"></i> Featured</span>
      <?php endif; ?>
    </div>
    <button type="button" class="event-card-save<?= $isSaved ? ' saved' : '' ?>" data-slug="<?= View::e($e['slug']) ?>"
            aria-pressed="<?= $isSaved ? 'true' : 'false' ?>" aria-label="<?= $isSaved ? 'Remove from saved' : 'Save event' ?>: <?= View::e($e['title']) ?>">
      <i class="<?= $isSaved ? 'fas' : 'far' ?> fa-heart" aria-hidden="true"></i>
    </button>
  </div>

  <div class="event-card-body">
    <?php if ($when): ?><div class="event-card-date"><?= View::e($when) ?></div><?php endif; ?>
    <h3 class="event-card-title"><a href="<?= View::e($url) ?>" class="stretched-link"><?= View::e($e['title']) ?></a></h3>
    <?php if (!empty($e['organizer_name'])): ?>
      <div class="event-card-by">by <?= View::e($e['organizer_name']) ?></div>
    <?php endif; ?>
    <?php if ($place !== ''): ?>
      <div class="event-card-place"><i class="fas <?= $isOnline ? 'fa-laptop' : 'fa-location-dot' ?>" aria-hidden="true"></i><span><?= View::e($place) ?></span></div>
    <?php endif; ?>
    <?php if (!empty($e['category_name']) || $saves > 0): ?>
      <div class="event-card-foot">
        <?php if (!empty($e['category_name'])): ?>
          <span class="event-card-category" style="color:<?= View::e($e['category_color'] ?? 'var(--text-secondary)') ?>">
            <?php if (!empty($e['category_icon'])): ?><i class="fas <?= View::e($e['category_icon']) ?>" aria-hidden="true"></i><?php endif; ?>
            <?= View::e($e['category_name']) ?>
          </span>
        <?php endif; ?>
        <?php if ($saves > 0): ?>
          <span class="event-card-saves"><i class="fas fa-heart" aria-hidden="true"></i> <?= $saves ?> saved</span>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</article>
