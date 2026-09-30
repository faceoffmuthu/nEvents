<?php
use NEvents\Core\View;
$appUrl    = rtrim($_ENV['APP_URL'] ?? '', '/');
$u         = $user ?? [];
$name      = $u['name'] ?? ($_SESSION['user_name'] ?? 'You');
$saved     = $saved ?? [];
$interests = $interests ?? [];
$myCount   = (int) ($my_events_count ?? 0);

// App-style menu rows: [href, icon, label, detail]
$menu = [
    'Activity' => [
        ['/my-events/saved', 'fa-heart',       'Saved events',  count($saved) ?: ''],
        ['/my-events',       'fa-calendar-check', 'My events',  $myCount ?: ''],
        ['/events/create',   'fa-plus',        'Post an event', ''],
    ],
    'Settings' => [
        ['/account',             'fa-user',   'Profile & contact',   ''],
        ['/account/interests',   'fa-shapes', 'Interests',           count($interests) ?: 'Add'],
        ['/account/preferences', 'fa-bell',   'Email notifications', ''],
    ],
    'More' => [
        ['/how-it-works', 'fa-circle-question', 'How N Events works', ''],
        ['/faq',          'fa-life-ring',       'Help & FAQ',         ''],
        ['/privacy',      'fa-shield-halved',   'Privacy & terms',    ''],
    ],
];
// The app has no website footer: its About / Contact / Terms links live here instead
if (\NEvents\Helpers\AppMode::active()) {
    $menu['More'] = [
        ['/about',        'fa-circle-info',     'About N Events',     ''],
        ['/how-it-works', 'fa-circle-question', 'How N Events works', ''],
        ['/faq',          'fa-life-ring',       'Help & FAQ',         ''],
        ['/contact',      'fa-envelope',        'Contact us',         ''],
        ['/privacy',      'fa-shield-halved',   'Privacy policy',     ''],
        ['/terms',        'fa-file-contract',   'Terms of service',   ''],
    ];
}
ob_start();
?>
<!-- ── Profile header ── -->
<section class="band-sand ne-profile-head">
  <div class="container-xl">
    <div class="ne-profile-id">
      <span class="ne-avatar ne-avatar-xl" aria-hidden="true"><?= View::e(mb_strtoupper(mb_substr($name, 0, 1))) ?></span>
      <div style="min-width:0">
        <h1 class="ne-profile-name"><?= View::e($name) ?></h1>
        <?php if (!empty($u['email'])): ?><div class="ne-profile-email"><?= View::e($u['email']) ?></div><?php endif; ?>
        <!-- Location: same picker/endpoint as the homepage (saves the preference) -->
        <div class="ne-profile-district">
          <?= View::partial('location-picker', ['district' => $district ?? null, 'allowAll' => true, 'autoSave' => true,
                'pickerId' => 'district-selector', 'ariaLabel' => 'Your location']) ?>
        </div>
      </div>
    </div>
    <div class="ne-profile-stats">
      <a href="<?= $appUrl ?>/my-events/saved"><strong><?= count($saved) ?></strong><span>Saved</span></a>
      <a href="<?= $appUrl ?>/my-events"><strong><?= $myCount ?></strong><span>My events</span></a>
      <a href="<?= $appUrl ?>/account/interests"><strong><?= count($interests) ?></strong><span>Interests</span></a>
    </div>
  </div>
</section>

<div class="band-cream ne-page-body">
  <div class="container-xl">
    <?php if (!empty($_SESSION['flash_success'])): ?>
      <div class="ne-alert ne-alert-success mb-3"><i class="fas fa-check-circle"></i> <?= View::e($_SESSION['flash_success']) ?></div>
      <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <div class="row g-4">
      <!-- Menu -->
      <div class="col-lg-4">
        <div class="ne-profile-menu-wrap">
          <?php foreach ($menu as $group => $rows): ?>
            <div class="ne-list-title"><?= $group ?></div>
            <nav class="ne-card ne-list" aria-label="<?= $group ?>">
              <?php foreach ($rows as [$href, $icon, $label, $detail]): ?>
                <a href="<?= $appUrl . $href ?>" class="ne-list-row">
                  <span class="ne-list-icon"><i class="fas <?= $icon ?>" aria-hidden="true"></i></span>
                  <span class="ne-list-label"><?= $label ?></span>
                  <?php if ($detail !== ''): ?><span class="ne-list-detail"><?= View::e((string) $detail) ?></span><?php endif; ?>
                  <i class="fas fa-chevron-right ne-list-chevron" aria-hidden="true"></i>
                </a>
              <?php endforeach; ?>
            </nav>
          <?php endforeach; ?>
          <form action="<?= $appUrl ?>/logout" method="POST" class="mt-3">
            <?= View::csrf() ?>
            <button type="submit" class="ne-card ne-list-row ne-list-signout w-100">
              <span class="ne-list-icon"><i class="fas fa-right-from-bracket" aria-hidden="true"></i></span>
              <span class="ne-list-label">Sign out</span>
            </button>
          </form>
        </div>
      </div>

      <!-- Content -->
      <div class="col-lg-8">
        <div class="ne-card ne-profile-interests">
          <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
            <h2 class="ne-profile-h2">Your interests</h2>
            <a href="<?= $appUrl ?>/account/interests" class="ne-section-link">Edit <i class="fas fa-pen fa-xs" aria-hidden="true"></i></a>
          </div>
          <?php if ($interests): ?>
            <div class="d-flex flex-wrap gap-2">
              <?php foreach ($interests as $cat): ?>
                <a href="<?= $appUrl ?>/discover?category=<?= (int) $cat['id'] ?>" class="ne-chip" style="--cat-color:<?= View::e($cat['color'] ?? 'var(--brand-primary)') ?>">
                  <i class="fas <?= View::e($cat['icon'] ?? 'fa-tag') ?>" style="color:var(--cat-color)" aria-hidden="true"></i> <?= View::e($cat['name']) ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="ne-profile-hint">Pick a few topics and the home page shows a <strong>For you</strong> row with matching events.</p>
            <a href="<?= $appUrl ?>/account/interests" class="btn-ne btn-ne-primary btn-ne-sm">Choose interests</a>
          <?php endif; ?>
        </div>

        <?php
          $rail_title = 'Upcoming in ' . ($district_name ?? 'India'); $rail_icon = 'fa-location-dot'; $rail_sub = null;
          $rail_link = $appUrl . '/discover' . (!empty($district_id) ? '?district=' . (int) $district_id : ''); $rail_events = $district_events ?? [];
          if ($rail_events) { include __DIR__ . '/../partials/event-rail.php'; }
          if ($saved) {
              $rail_title = 'Recently saved'; $rail_icon = 'fa-heart'; $rail_link = $appUrl . '/my-events/saved'; $rail_events = $saved;
              echo '<div class="mt-4">'; include __DIR__ . '/../partials/event-rail.php'; echo '</div>';
          }
        ?>
        <?php if (!($district_events ?? []) && !$saved): ?>
          <div class="ne-empty ne-card">
            <div class="ne-empty-icon" aria-hidden="true"><span></span><i class="fas fa-heart"></i></div>
            <h2>Nothing saved yet</h2>
            <p>Tap the heart on any event to keep it here — we'll email reminders before it starts.</p>
            <a href="<?= $appUrl ?>/discover" class="btn-ne btn-ne-primary">Discover events</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
