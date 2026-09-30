<?php
use NEvents\Core\View;
$appUrl  = rtrim($_ENV['APP_URL'] ?? '', '/');
$filters = $filters ?? [];
$query   = $query ?? [];

// Link to /discover with the current filters, some changed / removed (page always resets)
$link = function (array $set = [], array $unset = []) use ($appUrl, $query): string {
    $q = array_diff_key(array_merge($query, $set), array_flip(array_merge($unset, ['page'])));
    $q = array_filter($q, fn($v) => $v !== null && $v !== '');
    return $appUrl . '/discover' . ($q ? '?' . http_build_query($q) : '');
};

$whenLabels = ['today' => 'Today', 'tomorrow' => 'Tomorrow', 'weekend' => 'This weekend', 'week' => 'This week'];
$typeLabels = ['offline' => 'In person', 'online' => 'Online', 'hybrid' => 'Hybrid'];
$sortLabels = ['' => 'Recommended', 'soonest' => 'Soonest first', 'newest' => 'Newly added', 'popular' => 'Most saved'];

$when = $query['when'] ?? '';
if ($when === '' && (!empty($query['from']) || !empty($query['to']))) {
    $fmt = fn($d) => $d ? (new DateTimeImmutable($d))->format('j M') : '…';
    $whenText = $fmt($query['from'] ?? null) . ' – ' . $fmt($query['to'] ?? null);
} else {
    $whenText = $whenLabels[$when] ?? 'Any day';
}
// Location: a district (with its state), a whole state, or All India
$stateId   = (int) ($filters['state_id'] ?? 0);
$stateName = null;
foreach ($states ?? [] as $st) {
    if ((int) $st['id'] === $stateId) { $stateName = $st['name']; }
}
$districtName = !empty($district) ? $district['name'] : null;
$placeName    = $districtName ?? $stateName;
$activeCat   = (string) ($query['category'] ?? '');
$hasFilters  = (bool) array_diff_key($query, ['sort' => 1]);
// Inside the app: "Explore" in the top bar, results as a list, "Load more" instead of page numbers
$isApp = \NEvents\Helpers\AppMode::active();
if ($isApp) { $app_title = 'Explore'; }
ob_start();
?>
<div class="band-cream" style="min-height:100vh">

  <!-- Header: title + search -->
  <div class="band-sand ne-discover-head">
    <div class="container-xl">
      <h1 class="ne-discover-title"><span class="gradient-text">Discover</span> events</h1>
      <form method="GET" action="<?= $appUrl ?>/discover" class="ne-discover-search" role="search">
        <?php foreach (array_diff_key($query, ['q' => 1, 'page' => 1]) as $k => $v): ?>
          <input type="hidden" name="<?= View::e($k) ?>" value="<?= View::e($v) ?>">
        <?php endforeach; ?>
        <div class="ne-search-bar">
          <i class="fas fa-search ne-search-icon" aria-hidden="true"></i>
          <input type="search" name="q" id="search-input" value="<?= View::e($filters['search'] ?? '') ?>"
                 placeholder="Search events, topics, organizers…" class="ne-search-input" autocomplete="off" aria-label="Search events">
          <button type="submit" class="ne-search-btn" aria-label="Search"><i class="fas fa-search"></i></button>
        </div>
      </form>
    </div>
  </div>

  <div class="container-xl ne-discover-body">

    <!-- Category rail -->
    <nav class="ne-cat-rail" aria-label="Categories">
      <a href="<?= View::e($link([], ['category'])) ?>" class="ne-cat<?= $activeCat === '' ? ' active' : '' ?>"<?= $activeCat === '' ? ' aria-current="true"' : '' ?>>
        <span class="ne-cat-icon"><i class="fas fa-border-all"></i></span><span>All</span>
      </a>
      <?php foreach ($categories ?? [] as $cat): $on = $activeCat === (string) $cat['id']; ?>
        <a href="<?= View::e($link(['category' => $cat['id']])) ?>" class="ne-cat<?= $on ? ' active' : '' ?>"<?= $on ? ' aria-current="true"' : '' ?>
           style="--cat-color:<?= View::e($cat['color'] ?? 'var(--brand-primary)') ?>">
          <span class="ne-cat-icon"><i class="fas <?= View::e($cat['icon'] ?? 'fa-tag') ?>"></i></span><span><?= View::e($cat['name']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <!-- Filter chips: dropdown menus on desktop, bottom sheets on phones -->
    <div class="ne-chipbar" role="group" aria-label="Filters">
      <div class="dropdown">
        <button type="button" class="ne-chip<?= $whenText !== 'Any day' ? ' active' : '' ?>" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
          <i class="far fa-calendar" aria-hidden="true"></i> <?= View::e($whenText) ?> <i class="fas fa-chevron-down ne-chip-caret" aria-hidden="true"></i>
        </button>
        <div class="dropdown-menu ne-sheet">
          <div class="ne-sheet-title">When</div>
          <a class="dropdown-item<?= $whenText === 'Any day' ? ' active' : '' ?>" href="<?= View::e($link([], ['when', 'from', 'to'])) ?>">Any day</a>
          <?php foreach ($whenLabels as $k => $label): ?>
            <a class="dropdown-item<?= $when === $k ? ' active' : '' ?>" href="<?= View::e($link(['when' => $k], ['from', 'to'])) ?>"><?= $label ?></a>
          <?php endforeach; ?>
          <button type="button" class="dropdown-item" data-bs-toggle="offcanvas" data-bs-target="#filterSheet">Pick dates…</button>
        </div>
      </div>

      <div class="dropdown">
        <button type="button" class="ne-chip<?= !empty($query['format']) ? ' active' : '' ?>" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
          <i class="fas fa-location-dot" aria-hidden="true"></i> <?= View::e($typeLabels[$query['format'] ?? ''] ?? 'Any type') ?> <i class="fas fa-chevron-down ne-chip-caret" aria-hidden="true"></i>
        </button>
        <div class="dropdown-menu ne-sheet">
          <div class="ne-sheet-title">Type</div>
          <a class="dropdown-item<?= empty($query['format']) ? ' active' : '' ?>" href="<?= View::e($link([], ['format'])) ?>">Any type</a>
          <?php foreach ($typeLabels as $k => $label): ?>
            <a class="dropdown-item<?= ($query['format'] ?? '') === $k ? ' active' : '' ?>" href="<?= View::e($link(['format' => $k])) ?>"><?= $label ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <a class="ne-chip<?= !empty($query['free']) ? ' active' : '' ?>" href="<?= View::e(!empty($query['free']) ? $link([], ['free']) : $link(['free' => 1])) ?>" aria-pressed="<?= !empty($query['free']) ? 'true' : 'false' ?>">
        <i class="fas fa-gift" aria-hidden="true"></i> Free
      </a>

      <div class="dropdown">
        <button type="button" class="ne-chip<?= $placeName ? ' active' : '' ?>" data-bs-toggle="dropdown" data-bs-display="static" data-bs-auto-close="outside" aria-expanded="false">
          <i class="fas fa-map" aria-hidden="true"></i> <?= View::e($placeName ?? 'All India') ?> <i class="fas fa-chevron-down ne-chip-caret" aria-hidden="true"></i>
        </button>
        <div class="dropdown-menu ne-sheet ne-sheet-tall ne-loc-sheet">
          <div class="ne-sheet-title">Location</div>
          <div class="ne-loc-sheet-search" data-navigate="<?= View::e($link([], ['district', 'state'])) ?>">
            <?= View::partial('location-picker', ['name' => 'district', 'pickerId' => 'discover-location', 'ariaLabel' => 'Search a city, district or state']) ?>
          </div>
          <a class="dropdown-item<?= !$placeName ? ' active' : '' ?>" href="<?= View::e($link([], ['district', 'state'])) ?>">All India</a>
          <?php if ($stateName): ?>
            <a class="dropdown-item ne-loc-back" href="<?= View::e($link([], ['district', 'state'])) ?>"><i class="fas fa-arrow-left fa-xs" aria-hidden="true"></i> All states</a>
            <div class="ne-sheet-subtitle"><?= View::e($stateName) ?></div>
            <a class="dropdown-item<?= !$districtName ? ' active' : '' ?>" href="<?= View::e($link(['state' => $stateId], ['district'])) ?>">All of <?= View::e($stateName) ?></a>
            <?php foreach ($districts ?? [] as $d): ?>
              <a class="dropdown-item<?= (int) ($filters['district_id'] ?? 0) === (int) $d['id'] ? ' active' : '' ?>" href="<?= View::e($link(['state' => $stateId, 'district' => $d['id']])) ?>"><?= View::e($d['name']) ?></a>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="ne-sheet-subtitle">States and union territories</div>
            <?php foreach ($states ?? [] as $st): ?>
              <a class="dropdown-item" href="<?= View::e($link(['state' => $st['id']], ['district'])) ?>"><?= View::e($st['name']) ?> <i class="fas fa-chevron-right fa-xs ne-loc-more" aria-hidden="true"></i></a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="dropdown">
        <button type="button" class="ne-chip" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
          <i class="fas fa-arrow-down-wide-short" aria-hidden="true"></i> <?= View::e($sortLabels[$query['sort'] ?? ''] ?? 'Recommended') ?> <i class="fas fa-chevron-down ne-chip-caret" aria-hidden="true"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-end ne-sheet">
          <div class="ne-sheet-title">Sort by</div>
          <?php foreach ($sortLabels as $k => $label): ?>
            <a class="dropdown-item<?= ($query['sort'] ?? '') === $k ? ' active' : '' ?>" href="<?= View::e($k === '' ? $link([], ['sort']) : $link(['sort' => $k])) ?>"><?= $label ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <button type="button" class="ne-chip" data-bs-toggle="offcanvas" data-bs-target="#filterSheet">
        <i class="fas fa-sliders" aria-hidden="true"></i> All filters
      </button>
      <?php if ($hasFilters): ?>
        <a class="ne-chip ne-chip-clear" href="<?= $appUrl ?>/discover"><i class="fas fa-xmark" aria-hidden="true"></i> Clear</a>
      <?php endif; ?>
    </div>

    <p class="ne-result-count" role="status">
      <strong><?= number_format($total ?? 0) ?></strong> upcoming event<?= ($total ?? 0) == 1 ? '' : 's' ?>
      <?= $placeName ? 'in ' . View::e($districtName ? $districtName . ', ' . $stateName : $placeName) : 'across India' ?>
      <?php if (!empty($filters['search'])): ?> for “<?= View::e($filters['search']) ?>”<?php endif; ?>
    </p>

    <?php if (empty($events)): ?>
      <div class="ne-empty ne-card">
        <div class="ne-empty-icon" aria-hidden="true"><span></span><i class="fas fa-magnifying-glass"></i></div>
        <h2>No events match</h2>
        <p>Try another date, a different district, or fewer filters.</p>
        <a href="<?= $appUrl ?>/discover" class="btn-ne btn-ne-primary">Clear filters</a>
      </div>
    <?php else: ?>
      <?php if ($isApp): ?>
        <div class="app-list" data-load-list>
          <?php foreach ($events as $event): ?>
            <?php include __DIR__ . '/../partials/event-row.php'; ?>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
      <div class="row g-3 g-md-4">
        <?php foreach ($events as $event): ?>
          <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
            <?php include __DIR__ . '/../partials/event-card.php'; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php
      $totalPages = (int) ceil(($total ?? 0) / ($per_page ?? 20));
      $cur = (int) ($page ?? 1);
      if ($totalPages > 1):
        $pageUrl = fn(int $p) => $appUrl . '/discover?' . http_build_query(array_merge($query, ['page' => $p]));
      ?>
        <?php if ($isApp): ?>
          <?php if ($cur < $totalPages): ?>
            <a href="<?= View::e($pageUrl($cur + 1)) ?>" class="app-load-more" data-load-more>Load more events</a>
          <?php endif; ?>
        <?php else: ?>
        <nav class="ne-pagination" aria-label="Pages">
          <?php if ($cur > 1): ?><a href="<?= View::e($pageUrl($cur - 1)) ?>" class="ne-page-btn" aria-label="Previous page"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
          <?php for ($p = max(1, $cur - 2); $p <= min($totalPages, $cur + 2); $p++): ?>
            <a href="<?= View::e($pageUrl($p)) ?>" class="ne-page-btn<?= $p === $cur ? ' active' : '' ?>"<?= $p === $cur ? ' aria-current="page"' : '' ?>><?= $p ?></a>
          <?php endfor; ?>
          <?php if ($cur < $totalPages): ?><a href="<?= View::e($pageUrl($cur + 1)) ?>" class="ne-page-btn" aria-label="Next page"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
        </nav>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- All filters: bottom sheet (custom dates, format, district, category, price) -->
<div class="offcanvas offcanvas-bottom ne-filter-sheet" tabindex="-1" id="filterSheet" aria-labelledby="filterSheetLabel">
  <div class="offcanvas-header">
    <h2 class="offcanvas-title" id="filterSheetLabel">All filters</h2>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <form method="GET" action="<?= $appUrl ?>/discover" id="filter-form">
      <?php foreach (array_intersect_key($query, ['q' => 1, 'sort' => 1]) as $k => $v): ?>
        <input type="hidden" name="<?= View::e($k) ?>" value="<?= View::e($v) ?>">
      <?php endforeach; ?>
      <?php $autoSubmit = false; include __DIR__ . '/../partials/event-filter-fields.php'; ?>
      <button type="submit" class="btn-ne btn-ne-primary w-100 justify-content-center mt-3">Show events</button>
    </form>
  </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
