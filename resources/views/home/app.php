<?php
use NEvents\Core\View;
/**
 * Home inside the Android / iOS app (HomeController picks this view in app mode;
 * the website keeps home/index.php). Straight into the content: greeting, search,
 * location, quick filters, categories, then the event rows — no marketing sections.
 */
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$hour   = (int) (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('G');
$greet  = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$first  = trim(explode(' ', trim((string) ($_SESSION['user_name'] ?? '')))[0] ?? '');
$here   = $city_name ?? 'India';   // not $place: the card / row partials set their own
$find   = fn(array $q) => $appUrl . '/discover?' . http_build_query($q + ['district' => $district_id ?? null]);

$quick = [
    ['Today',        'fa-clock',   ['when' => 'today']],
    ['Tomorrow',     'fa-sun',     ['when' => 'tomorrow']],
    ['This weekend', 'fa-mug-hot', ['when' => 'weekend']],
    ['This week',    'fa-calendar-week', ['when' => 'week']],
    ['Free',         'fa-gift',    ['free' => 1]],
    ['Online',       'fa-laptop',  ['format' => 'online']],
];
ob_start();
?>
<div class="app-home">

  <section class="app-home-head">
    <p class="app-greet"><?= View::e($greet . ($first !== '' ? ', ' . $first : '')) ?></p>
    <h1 class="app-home-title">What's on in <span><?= View::e($here) ?></span>?</h1>

    <form action="<?= $appUrl ?>/discover" method="GET" class="app-search" role="search">
      <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
      <input type="search" name="q" placeholder="Search events, topics, organizers" aria-label="Search events" autocomplete="off" enterkeyhint="search">
      <?php if (!empty($district_id)): ?><input type="hidden" name="district" value="<?= (int) $district_id ?>"><?php endif; ?>
    </form>

    <?= View::partial('location-picker', ['name' => 'district', 'district' => $district ?? null, 'allowAll' => true,
          'autoSave' => true, 'pickerClass' => 'app-loc-chip', 'pickerId' => 'district-selector', 'ariaLabel' => 'Your location']) ?>
  </section>

  <nav class="app-chips" aria-label="Quick filters">
    <?php foreach ($quick as [$label, $icon, $q]): ?>
      <a href="<?= View::e($find($q)) ?>" class="app-chip"><i class="fas <?= $icon ?>" aria-hidden="true"></i> <?= $label ?></a>
    <?php endforeach; ?>
  </nav>

  <section class="app-section">
    <nav class="ne-cat-rail" aria-label="Browse by category">
      <?php foreach ($categories as $cat): ?>
        <a href="<?= View::e($find(['category' => $cat['id']])) ?>" class="ne-cat"
           style="--cat-color:<?= View::e($cat['color'] ?? 'var(--brand-primary)') ?>">
          <span class="ne-cat-icon"><i class="fas <?= View::e($cat['icon'] ?? 'fa-calendar') ?>"></i></span><span><?= View::e($cat['name']) ?></span>
        </a>
      <?php endforeach; ?>
      <a href="<?= $appUrl ?>/discover" class="ne-cat"><span class="ne-cat-icon"><i class="fas fa-ellipsis"></i></span><span>More</span></a>
    </nav>
  </section>

  <?php if (!empty($featured_events)): ?>
    <section class="app-section">
      <?php
        $rail_title = 'Upcoming in ' . $here; $rail_icon = null; $rail_sub = null;
        $rail_link = $find([]); $rail_events = $featured_events;
        include __DIR__ . '/../partials/event-rail.php';
      ?>
    </section>
  <?php else: ?>
    <section class="app-section">
      <div class="app-empty">
        <i class="fas fa-calendar-xmark" aria-hidden="true"></i>
        <h2>No upcoming events in <?= View::e($here) ?> yet</h2>
        <p>Try another place, or see what's happening online.</p>
        <a href="<?= $appUrl ?>/discover?format=online" class="btn-ne btn-ne-primary">Online events</a>
      </div>
    </section>
  <?php endif; ?>

  <?php
  $rows = [
      ['For you',         null, 'Based on your interests', $find([]),                     $for_you ?? []],
      ['Happening today', null, null,                      $find(['when' => 'today']),    $today_events ?? []],
      ['This weekend',    null, null,                      $find(['when' => 'weekend']),  $weekend_events ?? []],
      ['Free events',     null, null,                      $find(['free' => 1]),          $free_events ?? []],
      ['Online events',   null, 'Join from anywhere',      $appUrl . '/discover?format=online', $online_events ?? []],
  ];
  foreach ($rows as [$rail_title, $rail_icon, $rail_sub, $rail_link, $rail_events]):
    if (!$rail_events) continue; ?>
    <section class="app-section">
      <?php include __DIR__ . '/../partials/event-rail.php'; ?>
    </section>
  <?php endforeach; ?>

  <?php foreach ([['Technology', 1, $tech_events ?? []], ['Startup & Business', 3, $startup_events ?? []]] as [$label, $catId, $list]):
    if (!$list) continue; ?>
    <section class="app-section">
      <div class="ne-section-header">
        <h2 class="ne-section-title"><?= View::e($label) ?></h2>
        <a href="<?= View::e($find(['category' => $catId])) ?>" class="ne-section-link">See all <i class="fas fa-chevron-right fa-xs" aria-hidden="true"></i></a>
      </div>
      <div class="app-list">
        <?php foreach (array_slice($list, 0, 3) as $event): ?>
          <?php include __DIR__ . '/../partials/event-row.php'; ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>

</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
