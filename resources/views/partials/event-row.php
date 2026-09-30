<?php
use NEvents\Core\View;
use NEvents\Services\Events\EventImageService;

/**
 * Event list row — the app's list item (app mode, see NEvents\Helpers\AppMode):
 * square poster, date, title, place and price; the whole row opens the event,
 * the heart saves without leaving the screen (same .event-card-save as the card).
 * Expected: $event. Optional: $is_saved.
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
$isOnline = ($e['format'] ?? '') === 'online';
$place    = $isOnline ? 'Online' : trim(implode(', ', array_filter([
    $e['venue_name'] ?? null,
    ($e['city_name'] ?? null) ?: ($e['venue_locality'] ?? null),
])));
$price = null;
if (($e['pricing_type'] ?? '') === 'free') {
    $price = 'Free';
} elseif (($e['pricing_type'] ?? '') === 'paid' && !empty($e['min_price'])) {
    $price = (($e['currency'] ?? 'INR') === 'INR' ? '₹' : $e['currency'] . ' ') . number_format((float) $e['min_price']);
}
?>
<article class="app-row">
  <div class="app-row-thumb">
    <img src="<?= View::e($image['url']) ?>" alt="" loading="lazy" decoding="async">
  </div>
  <div class="app-row-body">
    <?php if ($when): ?><div class="app-row-date"><?= View::e($when) ?></div><?php endif; ?>
    <h3 class="app-row-title"><a href="<?= View::e($url) ?>" class="stretched-link"><?= View::e($e['title']) ?></a></h3>
    <div class="app-row-meta">
      <?php if ($place !== ''): ?><span class="app-row-place"><i class="fas <?= $isOnline ? 'fa-laptop' : 'fa-location-dot' ?>" aria-hidden="true"></i> <?= View::e($place) ?></span><?php endif; ?>
      <?php if ($price !== null): ?><span class="app-row-price<?= $price === 'Free' ? ' is-free' : '' ?>"><?= View::e($price) ?></span><?php endif; ?>
    </div>
  </div>
  <button type="button" class="event-card-save app-row-save<?= $isSaved ? ' saved' : '' ?>" data-slug="<?= View::e($e['slug']) ?>"
          aria-pressed="<?= $isSaved ? 'true' : 'false' ?>" aria-label="<?= $isSaved ? 'Remove from saved' : 'Save event' ?>: <?= View::e($e['title']) ?>">
    <i class="<?= $isSaved ? 'fas' : 'far' ?> fa-heart" aria-hidden="true"></i>
  </button>
</article>
