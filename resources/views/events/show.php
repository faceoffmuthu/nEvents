<?php
use NEvents\Core\View;
use NEvents\Helpers\RichText;
use NEvents\Helpers\Slug;
use NEvents\Services\Events\EventImageService;
$appUrl   = rtrim($_ENV['APP_URL'] ?? '', '/');
$e        = $event;
$userId   = $_SESSION['user_id'] ?? null;
$isSaved  = $is_saved ?? false;
$image    = EventImageService::resolve($e);
$tz       = new DateTimeZone('Asia/Kolkata');

$firstOcc = $occurrences[0] ?? null;
$dateStr = $timeStr = $endStr = '';
if ($firstOcc) {
    $start   = (new DateTimeImmutable($firstOcc['start_at_utc'], new DateTimeZone('UTC')))->setTimezone($tz);
    $dateStr = $start->format('l, j F Y');
    $timeStr = $start->format('g:i A');
    if (!empty($firstOcc['end_at_utc'])) {
        $end    = (new DateTimeImmutable($firstOcc['end_at_utc'], new DateTimeZone('UTC')))->setTimezone($tz);
        $endStr = $end->format('Y-m-d') === $start->format('Y-m-d') ? $end->format('g:i A') : $end->format('j M, g:i A');
    }
}

// Price
$isFree  = ($e['pricing_type'] ?? '') === 'free';
$priceTx = $isFree ? 'Free'
    : (!empty($e['min_price']) ? '₹' . number_format((float) $e['min_price'])
        . (!empty($e['max_price']) && $e['max_price'] != $e['min_price'] ? ' – ₹' . number_format((float) $e['max_price']) : '')
    : 'Price on website');

// Registration deadline (stored UTC)
$deadlineRaw = $firstOcc['registration_deadline'] ?? ($e['registration_deadline'] ?? null);
$deadline    = $deadlineRaw ? (new DateTimeImmutable($deadlineRaw, new DateTimeZone('UTC')))->setTimezone($tz) : null;
$deadlineSoon = $deadline && $deadline->getTimestamp() - time() < 48 * 3600;

$isOnline = ($e['format'] ?? '') === 'online';
$place    = $e['district_name'] ?? null ?: ($e['venue_locality'] ?? null);
$locationLine = implode(', ', array_filter([$e['venue_locality'] ?? null, $e['district_name'] ?? null, $e['venue_postal_code'] ?? null]));
$mapUrl = null;
if (!$isOnline && !empty($e['venue_name'])) {
    $mapUrl = !empty($e['venue_map_url']) ? $e['venue_map_url']
        : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(implode(', ', array_filter([$e['venue_name'], $e['venue_address'] ?? null, $place])));
}

// Summary: a person's own short summary as written; but when it is just the (cut-off) start of
// the description — as imported summaries are — rebuild it cleanly from the full description.
$summaryRaw = RichText::plain((string) ($e['short_summary'] ?? ''));
$descPlain  = RichText::plain((string) ($e['description'] ?? ''));
// Some sources only publish a shortened description in their structured data (it stops mid-word)
$descCutOff = trim((string) ($e['description'] ?? '')) !== '' && !preg_match('/[.!?)"\'”’»:]\s*$/u', rtrim((string) $e['description']));
$fromDesc   = $summaryRaw === '' || ($descPlain !== '' && str_starts_with($descPlain, rtrim($summaryRaw, '.… ')));
$summary    = $fromDesc ? RichText::plain((string) ($e['description'] ?? $e['short_summary'] ?? ''), 220) : RichText::plain($summaryRaw, 300);
if ($fromDesc && $descCutOff && !str_ends_with($summary, '…')) {
    $summary = preg_replace('/\s+\S*$/u', '', $summary) . '…';   // drop the half word
}
$regState = $reg_state ?? 'open';
$regUrl   = $appUrl . '/event/' . $e['slug'] . '/register';
$icsUrl   = $appUrl . '/event/' . $e['slug'] . '/calendar.ics';
$host     = $e['organizer_name'] ?? null;
// Organizer pages are keyed by the organizer's name (see OrganizerController)
$e['organizer_slug'] = trim((string) $host) !== '' ? Slug::make((string) $host) : null;

// Schema.org JSON-LD
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type'    => 'Event',
    'name'     => $e['title'],
    'description' => $summary,
    'eventStatus' => 'https://schema.org/EventScheduled',
];
if ($firstOcc) {
    $jsonLd['startDate'] = gmdate('c', strtotime($firstOcc['start_at_utc'] . ' UTC'));
    if ($firstOcc['end_at_utc']) $jsonLd['endDate'] = gmdate('c', strtotime($firstOcc['end_at_utc'] . ' UTC'));
}
if (($e['status'] ?? '') === 'cancelled') $jsonLd['eventStatus'] = 'https://schema.org/EventCancelled';
if (!empty($e['venue_name'])) {
    $jsonLd['location'] = ['@type' => 'Place', 'name' => $e['venue_name'], 'address' => $e['venue_address'] ?? ''];
}
if (!empty($e['featured_image_url'])) $jsonLd['image'] = $e['featured_image_url'];
if (!empty($e['registration_url'])) {
    $jsonLd['offers'] = [
        '@type'         => 'Offer',
        'url'           => $e['registration_url'],
        'price'         => $isFree ? '0' : ($e['min_price'] ?? ''),
        'priceCurrency' => $e['currency'] ?? 'INR',
    ];
}

$body_class = 'has-actionbar';   // phones: sticky Register bar replaces the tab bar on this screen
$shareData  = View::e(json_encode(['title' => $e['title'], 'url' => $appUrl . '/event/' . $e['slug']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
// Inside the app: share / save in the top bar (the bottom bar keeps price + Register),
// the category as the bar title (the event's name is right below, in large type)
$isApp = \NEvents\Helpers\AppMode::active();
if ($isApp) {
    $app_title   = $categories[0]['name'] ?? 'Event';
    $app_actions = '<button type="button" class="ne-appbar-btn" data-share="' . $shareData . '" aria-label="Share event"><i class="fas fa-share-nodes" aria-hidden="true"></i></button>'
                 . '<button type="button" class="ne-appbar-btn event-card-save' . ($isSaved ? ' saved' : '') . '" data-slug="' . View::e($e['slug']) . '" aria-pressed="' . ($isSaved ? 'true' : 'false') . '" aria-label="Save event">'
                 . '<i class="' . ($isSaved ? 'fas' : 'far') . ' fa-heart" aria-hidden="true"></i></button>';
}
ob_start();
?>
<!-- Schema.org -->
<script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

<div class="band-cream" style="min-height:100vh">

  <!-- ── Header: image first on phones ── -->
  <div class="band-sand ne-ev-head">
    <div class="ne-ev-head-blur" style="background-image:url('<?= View::e($image['url']) ?>')" aria-hidden="true"></div>
    <div class="container-xl" style="position:relative">
      <div class="row align-items-center g-4">
        <div class="col-lg-5 order-lg-2">
          <div class="ne-ev-image">
            <img src="<?= View::e($image['url']) ?>" alt="<?= View::e($image['alt']) ?>" fetchpriority="high">
          </div>
        </div>
        <div class="col-lg-7 order-lg-1">
          <nav class="ne-ev-crumbs d-none d-md-block" aria-label="Breadcrumb">
            <a href="<?= $appUrl ?>/">Home</a> /
            <?php if (!empty($categories[0])): ?>
              <a href="<?= $appUrl ?>/discover?category=<?= (int) $categories[0]['id'] ?>"><?= View::e($categories[0]['name']) ?></a> /
            <?php endif; ?>
            <span><?= View::e(mb_strimwidth($e['title'], 0, 48, '…')) ?></span>
          </nav>

          <div class="ne-ev-badges">
            <?php if ($regState === 'cancelled'): ?>
              <span class="badge-ne" style="background:var(--danger-soft);color:var(--danger);border:1px solid rgba(var(--danger-rgb),.3)"><i class="fas fa-ban fa-xs"></i> Cancelled</span>
            <?php elseif ($regState === 'completed'): ?>
              <span class="badge-ne" style="background:var(--bg-subtle);color:var(--text-secondary);border:1px solid var(--ne-border)"><i class="fas fa-flag-checkered fa-xs"></i> Completed</span>
            <?php endif; ?>
            <?php if (($e['verification_status'] ?? '') === 'verified'): ?>
              <span class="badge-ne badge-verified"><i class="fas fa-check-circle fa-xs"></i> Verified</span>
            <?php endif; ?>
            <span class="badge-ne badge-offline"><i class="fas <?= $isOnline ? 'fa-laptop' : 'fa-location-dot' ?> fa-xs"></i> <?= $isOnline ? 'Online' : (($e['format'] ?? '') === 'hybrid' ? 'Hybrid' : 'In person') ?></span>
            <?php if ($isFree): ?><span class="badge-ne badge-free">Free</span><?php endif; ?>
            <?php foreach ($categories as $cat): ?>
              <span class="badge-ne" style="background:var(--brand-primary-soft);color:var(--brand-primary);border:1px solid rgba(var(--brand-primary-rgb),.3)"><?= View::e($cat['name']) ?></span>
            <?php endforeach; ?>
          </div>

          <h1 class="ne-ev-title"><?= View::e($e['title']) ?></h1>

          <?php if ($host): ?>
            <div class="ne-ev-host">
              <span class="ne-avatar" aria-hidden="true"><?= View::e(mb_strtoupper(mb_substr($host, 0, 1))) ?></span>
              <span>Hosted by
                <?php if (!empty($e['organizer_slug'])): ?><a href="<?= $appUrl ?>/organizer/<?= View::e($e['organizer_slug']) ?>"><strong><?= View::e($host) ?></strong></a>
                <?php else: ?><strong><?= View::e($host) ?></strong><?php endif; ?>
              </span>
            </div>
          <?php endif; ?>
          <?php if (($e['data_origin'] ?? '') === 'user_submitted' && !empty($e['submitter_name'])): ?>
            <div class="ne-ev-credit"><i class="fas fa-user-pen fa-xs"></i> Posted by <?= View::e($e['submitter_name']) ?> · Community submitted</div>
          <?php endif; ?>

          <?php if ($summary !== ''): ?><p class="ne-ev-summary"><?= View::e($summary) ?></p><?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Body ── -->
  <div class="container-xl ne-ev-body">
    <div class="row g-4">
      <div class="col-lg-8">

        <!-- When / where -->
        <div class="ne-card ne-ev-facts">
          <?php if ($dateStr): ?>
          <div class="ne-ev-fact">
            <span class="ne-ev-fact-icon"><i class="far fa-calendar"></i></span>
            <div>
              <div class="ne-ev-fact-main"><?= View::e($dateStr) ?></div>
              <div class="ne-ev-fact-sub"><?= View::e($timeStr) ?><?= $endStr ? ' – ' . View::e($endStr) : '' ?> IST</div>
              <?php if (count($occurrences) > 1): ?>
                <div class="ne-ev-fact-sub">+<?= count($occurrences) - 1 ?> more session<?= count($occurrences) > 2 ? 's' : '' ?></div>
              <?php endif; ?>
              <button type="button" class="ne-ev-fact-link" data-bs-toggle="offcanvas" data-bs-target="#calendarSheet"><i class="fas fa-calendar-plus fa-xs"></i> Add to calendar</button>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($e['venue_name']) && !$isOnline): ?>
          <div class="ne-ev-fact">
            <span class="ne-ev-fact-icon"><i class="fas fa-location-dot"></i></span>
            <div>
              <div class="ne-ev-fact-main"><?= View::e($e['venue_name']) ?></div>
              <?php if (!empty($e['venue_address'])): ?><div class="ne-ev-fact-sub"><?= View::e($e['venue_address']) ?></div><?php endif; ?>
              <?php if ($locationLine !== '' && stripos((string) ($e['venue_address'] ?? ''), $locationLine) === false): ?><div class="ne-ev-fact-sub"><?= View::e($locationLine) ?></div><?php endif; ?>
              <?php if ($mapUrl): ?>
                <a href="<?= View::e($mapUrl) ?>" target="_blank" rel="noopener noreferrer" class="ne-ev-fact-link"><i class="fas fa-map-location-dot fa-xs"></i> Open in Maps</a>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>

          <?php if (in_array($e['format'] ?? '', ['online', 'hybrid'], true)): ?>
          <div class="ne-ev-fact">
            <span class="ne-ev-fact-icon"><i class="fas fa-laptop"></i></span>
            <div>
              <div class="ne-ev-fact-main">Online<?= !empty($e['online_platform']) ? ' · ' . View::e($e['online_platform']) : '' ?></div>
              <?php if (!empty($e['online_url'])): ?>
                <a href="<?= View::e($e['online_url']) ?>" target="_blank" rel="noopener noreferrer nofollow ugc" class="ne-ev-fact-link">Event information page</a>
              <?php else: ?>
                <div class="ne-ev-fact-sub">The link is shared by the organizer after registration</div>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>

          <div class="ne-ev-fact">
            <span class="ne-ev-fact-icon"><i class="fas fa-ticket"></i></span>
            <div>
              <div class="ne-ev-fact-main"><?= View::e($priceTx) ?></div>
              <?php if ($deadline): ?>
                <div class="ne-ev-fact-sub<?= $deadlineSoon ? ' is-urgent' : '' ?>"><?= $deadlineSoon ? '<i class="fas fa-triangle-exclamation fa-xs"></i> ' : '' ?>Registration closes <?= View::e($deadline->format('j M, g:i A')) ?></div>
              <?php else: ?>
                <div class="ne-ev-fact-sub">Registration on the organizer's page</div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- About -->
        <?php if (!empty($e['description'])): ?>
        <section class="ne-card ne-ev-about" aria-labelledby="about-h">
          <h2 id="about-h">About this event</h2>
          <?php
            // Some sources only publish a shortened description in their structured data;
            // show it as cut off and send people to the original page for the rest.
            $desc    = rtrim((string) $e['description']);
            $cutOff  = $descCutOff;
            $srcHost = !empty($source_url) ? preg_replace('/^www\./', '', (string) parse_url($source_url, PHP_URL_HOST)) : null;
          ?>
          <div class="ne-prose"><?= RichText::toHtml($cutOff ? rtrim((string) preg_replace('/[*_`\\\\]+$/', '', $desc)) . '…' : $desc) ?></div>
          <?php if ($srcHost): ?>
            <a href="<?= View::e($source_url) ?>" target="_blank" rel="noopener noreferrer nofollow" class="ne-ev-fact-link">
              <i class="fas fa-arrow-up-right-from-square fa-xs"></i> <?= $cutOff ? 'Continue reading on' : 'Original listing on' ?> <?= View::e($srcHost) ?>
            </a>
          <?php endif; ?>
        </section>
        <?php endif; ?>

        <!-- Tags -->
        <?php if (!empty($tags)): ?>
        <div class="ne-ev-tags">
          <?php foreach ($tags as $tag): ?>
            <a href="<?= $appUrl ?>/discover?q=<?= urlencode($tag['name']) ?>" class="ne-chip">#<?= View::e($tag['name']) ?></a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Organizer -->
        <?php if ($host): ?>
        <section class="ne-card ne-ev-org" aria-label="Organizer">
          <span class="ne-avatar ne-avatar-lg" aria-hidden="true"><?= View::e(mb_strtoupper(mb_substr($host, 0, 1))) ?></span>
          <div style="min-width:0">
            <div class="ne-ev-fact-sub">Organized by</div>
            <?php if (!empty($e['organizer_slug'])): ?>
              <a href="<?= $appUrl ?>/organizer/<?= View::e($e['organizer_slug']) ?>" class="ne-ev-org-name"><?= View::e($host) ?></a>
            <?php else: ?>
              <div class="ne-ev-org-name"><?= View::e($host) ?></div>
            <?php endif; ?>
            <div class="ne-ev-org-links">
              <?php if (!empty($e['organizer_website'])): ?><a href="<?= View::e($e['organizer_website']) ?>" target="_blank" rel="noopener noreferrer nofollow ugc"><i class="fas fa-globe fa-xs"></i> Website</a><?php endif; ?>
              <?php if (!empty($e['organizer_social_url'])): ?><a href="<?= View::e($e['organizer_social_url']) ?>" target="_blank" rel="noopener noreferrer nofollow ugc"><i class="fas fa-share-nodes fa-xs"></i> Social</a><?php endif; ?>
              <?php if (!empty($e['organizer_email'])): ?><a href="mailto:<?= View::e($e['organizer_email']) ?>"><i class="fas fa-envelope fa-xs"></i> Email</a><?php endif; ?>
              <?php if (!empty($e['organizer_phone'])): ?><a href="tel:<?= View::e($e['organizer_phone']) ?>"><i class="fas fa-phone fa-xs"></i> <?= View::e($e['organizer_phone']) ?></a><?php endif; ?>
            </div>
          </div>
        </section>
        <?php endif; ?>

        <div class="ne-ev-report">
          <?php if ($userId): ?>
            <button type="button" data-bs-toggle="modal" data-bs-target="#reportModal"><i class="fas fa-flag fa-xs"></i> Report this event</button>
          <?php else: ?>
            <a href="<?= $appUrl ?>/login">Sign in to report this event</a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Desktop: sticky action card -->
      <div class="col-lg-4 d-none d-lg-block">
        <aside class="ne-card ne-ev-cta" aria-label="Registration">
          <div class="ne-ev-cta-price"><?= View::e($priceTx) ?></div>
          <?php if ($deadline): ?><div class="ne-ev-fact-sub<?= $deadlineSoon ? ' is-urgent' : '' ?>" style="margin-bottom:1rem">Registration closes <?= View::e($deadline->format('j M, g:i A')) ?></div><?php endif; ?>

          <?php if ($regState === 'open'): ?>
            <a href="<?= View::e($regUrl) ?>" class="btn-ne btn-ne-primary btn-ne-lg w-100 justify-content-center"><i class="fas fa-arrow-up-right-from-square"></i> Register Now</a>
            <p class="ne-ev-cta-note">You'll continue on the organizer's registration page</p>
          <?php elseif ($regState === 'cancelled'): ?>
            <button type="button" class="btn-ne btn-ne-lg w-100 justify-content-center ne-btn-state is-cancelled" disabled><i class="fas fa-ban"></i> Event cancelled</button>
          <?php else: ?>
            <button type="button" class="btn-ne btn-ne-lg w-100 justify-content-center ne-btn-state" disabled><i class="fas fa-flag-checkered"></i> Registration closed</button>
          <?php endif; ?>

          <div class="ne-ev-cta-actions">
            <button type="button" class="event-card-save btn-ne btn-ne-ghost<?= $isSaved ? ' saved' : '' ?>" data-slug="<?= View::e($e['slug']) ?>" aria-pressed="<?= $isSaved ? 'true' : 'false' ?>">
              <i class="<?= $isSaved ? 'fas' : 'far' ?> fa-heart"></i> <span>Save</span>
            </button>
            <button type="button" class="btn-ne btn-ne-ghost" data-share="<?= $shareData ?>"><i class="fas fa-share-nodes"></i> Share</button>
            <button type="button" class="btn-ne btn-ne-ghost" data-bs-toggle="offcanvas" data-bs-target="#calendarSheet"><i class="fas fa-calendar-plus"></i> Calendar</button>
          </div>
        </aside>
      </div>
    </div>

    <!-- Similar events -->
    <?php if (!empty($related) && $isApp): ?>
    <section class="ne-ev-related app-section" aria-labelledby="similar-h">
      <div class="ne-section-header">
        <h2 class="ne-section-title" id="similar-h">Similar events</h2>
        <?php if (!empty($categories[0])): ?><a href="<?= $appUrl ?>/discover?category=<?= (int) $categories[0]['id'] ?>" class="ne-section-link">See all <i class="fas fa-chevron-right fa-xs" aria-hidden="true"></i></a><?php endif; ?>
      </div>
      <?php (function () use ($related) {
          $grid_events = array_slice($related, 0, 5); $grid_saved = false;
          include __DIR__ . '/../partials/event-grid.php';
      })(); ?>
    </section>
    <?php elseif (!empty($related)): ?>
    <div class="ne-ev-related">
      <?php (function () use ($related, $appUrl, $categories) {
          $rail_title = 'Similar events'; $rail_icon = 'fa-shapes'; $rail_sub = null;
          $rail_link  = !empty($categories[0]) ? $appUrl . '/discover?category=' . (int) $categories[0]['id'] : null;
          $rail_events = $related;
          include __DIR__ . '/../partials/event-rail.php';
      })(); ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ── Phones: sticky action bar (price · save · share · Register) ── -->
<div class="ne-actionbar d-lg-none" role="region" aria-label="Registration">
  <div class="ne-actionbar-price">
    <?php if ($dateStr): ?><strong><?= View::e($start->format('D, j M · g:i A')) ?></strong><?php endif; ?>
    <span><?= View::e($priceTx) ?></span>
  </div>
  <button type="button" class="ne-actionbar-icon event-card-save<?= $isSaved ? ' saved' : '' ?>" data-slug="<?= View::e($e['slug']) ?>" aria-pressed="<?= $isSaved ? 'true' : 'false' ?>" aria-label="Save event">
    <i class="<?= $isSaved ? 'fas' : 'far' ?> fa-heart"></i>
  </button>
  <button type="button" class="ne-actionbar-icon" data-share="<?= $shareData ?>" aria-label="Share event"><i class="fas fa-share-nodes"></i></button>
  <?php if ($regState === 'open'): ?>
    <a href="<?= View::e($regUrl) ?>" class="btn-ne btn-ne-primary ne-actionbar-cta">Register</a>
  <?php else: ?>
    <button type="button" class="btn-ne ne-actionbar-cta ne-btn-state<?= $regState === 'cancelled' ? ' is-cancelled' : '' ?>" disabled><?= $regState === 'cancelled' ? 'Cancelled' : 'Closed' ?></button>
  <?php endif; ?>
</div>

<!-- ── Add to calendar sheet ── -->
<div class="offcanvas offcanvas-bottom ne-filter-sheet" tabindex="-1" id="calendarSheet" aria-labelledby="calendarSheetLabel">
  <div class="offcanvas-header">
    <h2 class="offcanvas-title" id="calendarSheetLabel">Add to calendar</h2>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body ne-sheet-list">
    <?php if (!empty($gcal_url)): ?>
      <a href="<?= View::e($gcal_url) ?>" target="_blank" rel="noopener noreferrer"><i class="fab fa-google"></i> Google Calendar</a>
    <?php endif; ?>
    <a href="<?= View::e($icsUrl) ?>"><i class="fab fa-apple"></i> Apple Calendar</a>
    <a href="<?= View::e($icsUrl) ?>"><i class="fas fa-calendar-days"></i> Outlook / other (.ics)</a>
  </div>
</div>

<?php if ($userId): ?>
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="reportForm">
      <div class="modal-header" style="border-color:var(--ne-border)">
        <h2 class="modal-title" id="reportModalLabel" style="font-size:1.05rem;font-weight:800">Report this event</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="ne-form-group">
          <label class="ne-form-label" for="reportReason">What's wrong?</label>
          <select id="reportReason" name="reason" class="ne-form-control" required>
            <option value="">Choose a reason…</option>
            <?php foreach (\NEvents\Services\Moderation\ModerationService::REPORT_REASONS as $code => $label): ?>
              <option value="<?= $code ?>"><?= View::e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="ne-form-group mb-0">
          <label class="ne-form-label" for="reportDetails">Details (optional)</label>
          <textarea id="reportDetails" name="details" rows="3" maxlength="1000" class="ne-form-control" placeholder="Anything that helps our moderators check it"></textarea>
        </div>
      </div>
      <div class="modal-footer" style="border-color:var(--ne-border)">
        <button type="button" class="btn-ne btn-ne-ghost btn-ne-sm" data-bs-dismiss="modal">Close</button>
        <button type="submit" class="btn-ne btn-ne-primary btn-ne-sm">Send Report</button>
      </div>
    </form>
  </div>
</div>
<script>
document.getElementById('reportForm').addEventListener('submit', async function (ev) {
  ev.preventDefault();
  const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const body = new URLSearchParams(new FormData(this));
  body.append('_csrf', token);
  try {
    const res = await fetch(<?= json_encode($appUrl . '/event/' . $e['slug'] . '/report', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, {
      method: 'POST',
      headers: { 'X-CSRF-Token': token, 'X-Requested-With': 'XMLHttpRequest' },
      body,
    });
    const d = await res.json();
    NEvents.showToast(d.success ? d.message : (d.error || 'Could not send the report.'), d.success ? 'success' : 'error');
    if (d.success) bootstrap.Modal.getInstance(document.getElementById('reportModal')).hide();
  } catch (e) {
    NEvents.showToast('Could not send the report. Please try again.', 'error');
  }
});
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
