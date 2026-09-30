<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
// Espresso hero, then Ivory / Sand sections alternating, in the order they are actually rendered
// (empty sections are skipped, so the pattern never breaks).
$band = function (): string { static $i = 0; $n = $i++; return $n === 0 ? 'band-hero' : ($n % 2 === 1 ? 'band-cream' : 'band-sand'); };

ob_start();
?>

<!-- ══════════════════════════════════════════════════════════
     HERO SECTION
════════════════════════════════════════════════════════════ -->
<section class="ne-hero <?= $band() ?>">
  <div class="ne-hero-grain" aria-hidden="true"></div>

  <div class="container-xl ne-hero-content">
    <div class="row align-items-center g-4 g-lg-5">
      <div class="col-lg-6 col-xl-7">
        <!-- Eyebrow -->
        <div class="ne-hero-eyebrow">
          <i class="fas fa-location-dot"></i>
          Event discovery across India
        </div>

        <!-- Title -->
        <h1 class="ne-hero-title">
          Stop searching for events.<br>
          Let the right events <span class="ne-highlight">find you.</span>
        </h1>

        <!-- Subtitle -->
        <p class="ne-hero-subtitle">
          Discover tech meetups, startup events, workshops, cycling rides, and cultural events
          across <?= View::e($city_name ?? 'Chennai') ?> — all in one place, personalized just for you.
        </p>

        <!-- Search Bar -->
        <form action="<?= $appUrl ?>/search" method="GET" class="ne-search-bar ne-hero-search mb-4" role="search">
          <i class="fas fa-search ne-search-icon" aria-hidden="true"></i>
          <input type="text" name="q" id="search-input"
                 placeholder="Search events, topics, organizers…"
                 class="ne-search-input"
                 aria-label="Search events"
                 autocomplete="off">
          <div class="ne-search-divider"></div>
          <?= View::partial('location-picker', ['name' => 'district', 'district' => $district ?? null, 'allowAll' => true,
                'autoSave' => true, 'pickerClass' => 'ne-city-select', 'pickerId' => 'district-selector', 'ariaLabel' => 'Your location']) ?>
          <button type="submit" class="ne-search-btn">
            <i class="fas fa-search"></i>
            <span class="d-none d-sm-inline">Find Events</span>
          </button>
        </form>

        <!-- Date shortcuts -->
        <div class="ne-date-tabs">
          <a href="<?= $appUrl ?>/events/today"       class="ne-date-tab">Today</a>
          <a href="<?= $appUrl ?>/events/tomorrow"     class="ne-date-tab">Tomorrow</a>
          <a href="<?= $appUrl ?>/events/this-weekend" class="ne-date-tab">This Weekend</a>
          <a href="<?= $appUrl ?>/events/this-week"    class="ne-date-tab">This Week</a>
          <a href="<?= $appUrl ?>/events/free"         class="ne-date-tab"><i class="fas fa-gift fa-xs"></i> Free</a>
          <a href="<?= $appUrl ?>/events/online"       class="ne-date-tab"><i class="fas fa-laptop fa-xs"></i> Online</a>
        </div>

        <!-- Stats -->
        <div class="ne-hero-stats">
          <div class="ne-stat-item">
            <div class="ne-stat-number" data-counter="<?= $total_events ?? 0 ?>" data-suffix="+"><?= number_format($total_events ?? 0) ?>+</div>
            <div class="ne-stat-label">Live Events</div>
          </div>
          <div class="ne-stat-item">
            <div class="ne-stat-number" data-counter="<?= (int) ($total_cities ?? 0) ?>" data-suffix=""><?= number_format($total_cities ?? 0) ?></div>
            <div class="ne-stat-label">Cities</div>
          </div>
          <div class="ne-stat-item">
            <div class="ne-stat-number" data-counter="50" data-suffix="+">50+</div>
            <div class="ne-stat-label">Categories</div>
          </div>
        </div>
      </div>

      <!-- Hero visual: a fanned stack of real upcoming events (a swipeable strip on phones) -->
      <?php
        $heroEvents = array_slice($featured_events ?? [], 0, 3);
        if ($heroEvents):
      ?>
      <div class="col-lg-6 col-xl-5">
        <div class="ne-hero-stack" aria-label="Upcoming events">
          <?php foreach ($heroEvents as $i => $hev):
            $himg  = \NEvents\Services\Events\EventImageService::resolve($hev);
            $hwhen = !empty($hev['next_start'])
              ? strtoupper((new DateTimeImmutable($hev['next_start'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('D, j M · g:i A'))
              : '';
          ?>
            <a href="<?= $appUrl ?>/event/<?= View::e($hev['slug']) ?>" class="ne-hero-poster ne-hero-poster-<?= $i + 1 ?>">
              <img src="<?= View::e($himg['url']) ?>" alt="<?= View::e($himg['alt']) ?>" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
              <span class="ne-hero-poster-meta">
                <?php if ($hwhen): ?><span class="ne-hero-poster-date"><?= View::e($hwhen) ?></span><?php endif; ?>
                <strong><?= View::e($hev['title']) ?></strong>
              </span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     UPCOMING NEAR YOU (+ category shortcuts) — events first, like an app
════════════════════════════════════════════════════════════ -->
<section class="ne-section ne-section-rail <?= $band() ?>">
  <div class="container-xl">
    <nav class="ne-cat-rail" aria-label="Browse by category">
      <?php foreach ($categories as $cat): ?>
        <a href="<?= $appUrl ?>/discover?<?= http_build_query(['category' => $cat['id'], 'district' => $district_id ?? null]) ?>" class="ne-cat"
           style="--cat-color:<?= View::e($cat['color'] ?? 'var(--brand-primary)') ?>">
          <span class="ne-cat-icon"><i class="fas <?= View::e($cat['icon'] ?? 'fa-calendar') ?>"></i></span><span><?= View::e($cat['name']) ?></span>
        </a>
      <?php endforeach; ?>
      <a href="<?= $appUrl ?>/discover" class="ne-cat"><span class="ne-cat-icon"><i class="fas fa-ellipsis"></i></span><span>More</span></a>
    </nav>

    <?php if (!empty($featured_events)): ?>
      <?php
        $rail_title = 'Upcoming in ' . ($city_name ?? 'Chennai'); $rail_icon = 'fa-location-dot'; $rail_sub = null;
        $rail_link = $appUrl . '/discover?' . http_build_query(['district' => $district_id ?? null]); $rail_events = $featured_events;
        include __DIR__ . '/../partials/event-rail.php';
      ?>
    <?php else: ?>
      <div class="ne-empty ne-card">
        <div class="ne-empty-icon" aria-hidden="true"><span></span><i class="fas fa-magnifying-glass"></i></div>
        <h2>No upcoming events in <?= View::e($city_name ?? 'Chennai') ?> right now</h2>
        <p>We only show genuine events — never placeholders. Try another district or online events.</p>
        <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap">
          <a href="<?= $appUrl ?>/discover" class="btn-ne btn-ne-primary"><i class="fas fa-globe fa-xs"></i> All events across India</a>
          <a href="<?= $appUrl ?>/discover?format=online" class="btn-ne btn-ne-secondary"><i class="fas fa-laptop fa-xs"></i> Online events</a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php
// The remaining event rows: each renders only when it has events, and each takes the next band color
$rows = [
    ['For you',          'fa-wand-magic-sparkles', 'Based on your interests',                          $appUrl . '/discover?' . http_build_query(['district' => $district_id ?? null]), $for_you ?? []],
    ['Happening today',  'fa-clock',               count($today_events ?? []) . ' today in ' . ($city_name ?? 'Chennai'), $appUrl . '/discover?' . http_build_query(['when' => 'today', 'district' => $district_id ?? null]), $today_events ?? []],
    ['This weekend',     'fa-sun',                 'Plan your perfect weekend',                        $appUrl . '/discover?' . http_build_query(['when' => 'weekend', 'district' => $district_id ?? null]), $weekend_events ?? []],
    ['Free events',      'fa-gift',                'Zero cost, maximum value',                         $appUrl . '/discover?' . http_build_query(['free' => 1, 'district' => $district_id ?? null]), $free_events ?? []],
    ['Online events',    'fa-laptop',              'Join from anywhere',                               $appUrl . '/discover?format=online', $online_events ?? []],
];
foreach ($rows as [$rail_title, $rail_icon, $rail_sub, $rail_link, $rail_events]):
  if (!$rail_events) continue; ?>
<section class="ne-section ne-section-rail <?= $band() ?>">
  <div class="container-xl">
    <?php include __DIR__ . '/../partials/event-rail.php'; ?>
  </div>
</section>
<?php endforeach; ?>

<!-- ══════════════════════════════════════════════════════════
     TECH EVENTS + STARTUP EVENTS (compact lists)
════════════════════════════════════════════════════════════ -->
<?php if (!empty($tech_events) || !empty($startup_events)): ?>
<section class="ne-section ne-section-rail <?= $band() ?>">
  <div class="container-xl">
    <div class="row g-4">
      <?php foreach ([['Technology', 'fa-microchip', 'technology', $tech_events ?? []], ['Startup &amp; Business', 'fa-rocket', 'startup', $startup_events ?? []]] as [$label, $icon, $slug, $list]):
        if (!$list) continue; ?>
      <div class="col-lg-6">
        <div class="ne-section-header">
          <h2 class="ne-section-title" style="font-size:1.35rem"><i class="fas <?= $icon ?> ne-rail-icon" aria-hidden="true"></i><?= $label ?></h2>
          <a href="<?= $appUrl ?>/events/<?= View::e($city_slug ?? 'chennai') ?>/<?= $slug ?>" class="ne-section-link">See all <i class="fas fa-arrow-right fa-xs" aria-hidden="true"></i></a>
        </div>
        <div class="d-flex flex-column gap-3">
          <?php foreach (array_slice($list, 0, 3) as $event): ?>
            <?php include __DIR__ . '/../partials/event-mini.php'; ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════
     HOW IT WORKS
════════════════════════════════════════════════════════════ -->
<section class="ne-section <?= $band() ?>">
  <div class="container-xl">
    <div class="text-center mb-5 animate-on-scroll">
      <h2 class="ne-section-title d-inline-block" style="padding-left:0">How N Events Works</h2>
      <p class="ne-section-subtitle mt-2">Your personalized event intelligence engine</p>
    </div>

    <div class="ne-how-grid">
      <?php
      $steps = [
        ['num'=>1,'icon'=>'fa-sliders','title'=>'Set Your Interests','text'=>'Choose your city, select interest categories, and set a search radius. We learn what matters to you.'],
        ['num'=>2,'icon'=>'fa-radar','title'=>'We Discover Events','text'=>'Our platform continuously scans permitted sources, discovers events, removes duplicates and verifies information.'],
        ['num'=>3,'icon'=>'fa-brain','title'=>'Smart Ranking','text'=>'Events are scored and ranked based on your interests, location, format preference and event quality.'],
        ['num'=>4,'icon'=>'fa-bell','title'=>'Events Find You','text'=>'Receive personalized daily digests via email. Never manually search for events again.'],
      ];
      foreach ($steps as $step): ?>
        <div class="d-flex animate-on-scroll">
          <div class="how-step w-100">
            <div class="how-step-number"><?= $step['num'] ?></div>
            <div class="how-step-icon"><i class="fas <?= $step['icon'] ?>"></i></div>
            <h3 class="how-step-title"><?= $step['title'] ?></h3>
            <p class="how-step-text"><?= $step['text'] ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     CTA SECTION
════════════════════════════════════════════════════════════ -->
<section class="ne-section-sm <?= $band() ?>">
  <div class="container-xl">
    <div class="ne-cta-banner animate-on-scroll">
      <div style="display:inline-flex;align-items:center;gap:.5rem;font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--brand-primary);background:var(--bg-surface);border:1px solid rgba(var(--brand-primary-rgb),.22);padding:.4rem 1rem;border-radius:20px;margin-bottom:1.5rem">
        <i class="fas fa-user-plus"></i> Free Forever
      </div>
      <h2 style="font-size:clamp(1.75rem,4vw,3rem);font-weight:900;margin-bottom:1rem">
        Never miss another<br>
        <span class="gradient-text">relevant event</span>
      </h2>
      <p style="color:var(--ne-text-muted);max-width:520px;margin:0 auto 2rem;font-size:1.05rem;line-height:1.7">
        Join thousands of professionals, creators, and enthusiasts across India
        who let the right events find them — automatically.
      </p>
      <div class="d-flex gap-3 justify-content-center flex-wrap">
        <a href="<?= $appUrl ?>/register" class="btn-ne btn-ne-primary btn-ne-lg">
          <i class="fas fa-user-plus"></i> Create Free Account
        </a>
        <a href="<?= $appUrl ?>/discover" class="btn-ne btn-ne-secondary btn-ne-lg">
          <i class="fas fa-compass"></i> Browse Events
        </a>
      </div>

      <!-- For Organizers -->
      <div style="margin-top:3rem;padding-top:2rem;border-top:1px solid var(--ne-border)">
        <p style="color:var(--ne-text-muted);font-size:.875rem;margin-bottom:1rem">
          <strong style="color:var(--ne-text)">Organizing an event?</strong>
          Submit it free and reach thousands of interested participants.
        </p>
        <a href="<?= $appUrl ?>/events/create" class="btn-ne btn-ne-secondary btn-ne-sm">
          <i class="fas fa-plus"></i> Post Your Event
        </a>
      </div>
    </div>
  </div>
</section>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
