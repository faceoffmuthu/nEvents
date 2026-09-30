<?php
use NEvents\Core\View;
$appUrl  = rtrim($_ENV['APP_URL'] ?? '', '/');
$appName = $_ENV['APP_NAME'] ?? 'N Events';
$csrf    = $_SESSION['csrf_token'] ?? '';
$userId  = $_SESSION['user_id'] ?? null;
$flash      = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Current path (relative to APP_URL) for active navigation states
$__path = '/' . ltrim(substr((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), strlen((string) parse_url($appUrl, PHP_URL_PATH))), '/');
$navActive = fn(string $prefix) => ($prefix === '/' ? $__path === '/' : str_starts_with($__path, $prefix)) ? ' active' : '';
$__v = @filemtime(__DIR__ . '/../../../public/css/theme.css') . '-' . @filemtime(__DIR__ . '/../../../public/css/nevents.css');
// Mobile/tablet bottom tab bar (app shell) everywhere except the desktop-first admin area
$__tabbar = !str_starts_with($__path, '/admin');

// Inside the Android / iOS app: the app interface (top bar + tab bar, no website header,
// menu or footer). Admin and the organizer portal keep the website layout.
$__app = \NEvents\Helpers\AppMode::active() && !str_starts_with($__path, '/admin') && !str_starts_with($__path, '/organizer-portal');
if ($__app) {
    // Tab destinations and entry screens have no back arrow
    $__roots = ['/', '/discover', '/my-events/saved', '/dashboard', '/login', '/register', '/events/create', '/onboarding'];
    $__isRoot = in_array($__path, $__roots, true);
    // Where "back" goes when there is no previous screen in the app (e.g. opened from a shared link)
    $__parent = match (true) {
        str_starts_with($__path, '/event/'), str_starts_with($__path, '/events/'), str_starts_with($__path, '/category/'),
        str_starts_with($__path, '/search') => '/discover',
        str_starts_with($__path, '/organizer/') => '/organizers',
        str_starts_with($__path, '/account'), str_starts_with($__path, '/my-events'), str_starts_with($__path, '/notifications'),
        str_starts_with($__path, '/my-organizers') => '/dashboard',
        default => '/',
    };
    $__barTitle = $app_title ?? trim((string) preg_replace('/\s+[—–-]\s+N Events$/u', '', (string) ($title ?? $appName)));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="csrf-token" content="<?= View::e($csrf) ?>">
  <meta name="app-url" content="<?= View::e($appUrl) ?>">
  <title><?= View::e($title ?? $appName) ?></title>
  <meta name="description" content="<?= View::e($meta_desc ?? 'Discover the best events across India — tech meetups, startup events, workshops, cycling, networking and more.') ?>">
  <meta property="og:title" content="<?= View::e($title ?? $appName) ?>">
  <meta property="og:description" content="<?= View::e($meta_desc ?? '') ?>">
  <meta property="og:type" content="website">
  <?php if (!empty($event['featured_image_url'])): ?>
  <meta property="og:image" content="<?= View::e(\NEvents\Services\Events\EventImageService::resolve($event)['url']) ?>">
  <?php endif; ?>
  <meta name="twitter:card" content="summary_large_image">
  <?php if (!empty($canonical_url)): ?>
  <link rel="canonical" href="<?= View::e($canonical_url) ?>">
  <?php endif; ?>
  <!-- Brand icons (generated from the official logo by bin/build-brand-assets.ps1) -->
  <link rel="icon" href="<?= $appUrl ?>/images/brand/favicon-32.png" type="image/png" sizes="32x32">
  <link rel="icon" href="<?= $appUrl ?>/images/brand/favicon-192.png" type="image/png" sizes="192x192">
  <link rel="apple-touch-icon" href="<?= $appUrl ?>/images/brand/apple-touch-icon.png">
  <meta name="theme-color" content="#2A1B12">
  <!-- Installable web app ("Add to Home screen"); later wrapped as Android/iOS apps -->
  <link rel="manifest" href="<?= $appUrl ?>/manifest.webmanifest">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="N Events">

  <!-- Google Fonts: Fraunces (serif headings) + Inter (UI) + Montserrat (brand name, echoes the logo wordmark) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500;1,9..144,600&family=Inter:wght@400;500;600;700;800;900&family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">

  <!-- N Events: brand tokens first, then components -->
  <link rel="stylesheet" href="<?= $appUrl ?>/css/theme.css?v=<?= $__v ?>">
  <link rel="stylesheet" href="<?= $appUrl ?>/css/nevents.css?v=<?= $__v ?>">

  <?php if (!empty($extra_css)): ?>
  <style><?= $extra_css ?></style>
  <?php endif; ?>
</head>
<?php $__bodyClass = trim(($__app ? 'is-app ' : '') . ($__tabbar ? 'has-tabbar ' : '') . ($body_class ?? '')); /* views may add $body_class */ ?>
<body<?= $__bodyClass !== '' ? ' class="' . View::e($__bodyClass) . '"' : '' ?>>

<!-- ── Flash Data ── -->
<?php if ($flash): ?>
<div data-flash="success" data-msg="<?= View::e($flash) ?>" hidden></div>
<?php endif; ?>
<?php if ($flashError): ?>
<div data-flash="error" data-msg="<?= View::e($flashError) ?>" hidden></div>
<?php endif; ?>

<?php if ($__app): ?>
<?= View::partial('app-topbar', [
    'bar_title'   => $__barTitle,
    'bar_back'    => $__isRoot ? null : $appUrl . $__parent,
    'bar_brand'   => $__path === '/',
    'bar_actions' => $app_actions ?? '',
]) ?>
<?php else: ?>
<!-- ── Navigation ── -->
<nav class="ne-navbar">
  <div class="container-xl">
    <div class="d-flex align-items-center justify-content-between gap-3">

      <?= View::partial('brand-logo', ['logo_height' => 38]) ?>

      <!-- Desktop Nav -->
      <div class="d-none d-lg-flex align-items-center gap-1">
        <a href="<?= $appUrl ?>/discover"       class="ne-nav-link<?= $navActive('/discover') ?>">Discover</a>
        <a href="<?= $appUrl ?>/events/today"   class="ne-nav-link<?= $navActive('/events/today') ?>">Today</a>
        <a href="<?= $appUrl ?>/events/this-weekend" class="ne-nav-link<?= $navActive('/events/this-weekend') ?>">Weekend</a>
        <a href="<?= $appUrl ?>/events/free"    class="ne-nav-link<?= $navActive('/events/free') ?>">Free</a>
        <a href="<?= $appUrl ?>/events/online"  class="ne-nav-link<?= $navActive('/events/online') ?>">Online</a>
        <a href="<?= $appUrl ?>/organizers"     class="ne-nav-link<?= $navActive('/organizer') ?>">Organizers</a>
      </div>

      <!-- Quick Search -->
      <form action="<?= $appUrl ?>/search" method="GET" class="ne-nav-search d-none d-md-flex" role="search">
        <i class="fas fa-search" aria-hidden="true"></i>
        <input type="search" name="q" placeholder="Search events…" aria-label="Search events">
      </form>

      <!-- Auth -->
      <div class="d-flex align-items-center gap-2">
        <?php if ($userId): ?>
          <a href="<?= $appUrl ?>/events/create" class="btn-ne btn-ne-primary btn-ne-sm d-none d-lg-flex">
            <i class="fas fa-plus"></i> Post Event
          </a>
          <!-- phones/tablets: Profile tab + Sign out on the profile screen cover these -->
          <div class="dropdown d-none d-lg-block">
            <button class="btn-ne btn-ne-ghost btn-ne-sm" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
              <i class="fas fa-user-circle"></i> <span class="d-none d-md-inline">Me</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="<?= $appUrl ?>/dashboard"><i class="fas fa-home fa-fw"></i> Dashboard</a></li>
              <li><a class="dropdown-item" href="<?= $appUrl ?>/my-events"><i class="fas fa-calendar-check fa-fw"></i> My Events</a></li>
              <li><a class="dropdown-item" href="<?= $appUrl ?>/my-events/saved"><i class="fas fa-bookmark fa-fw"></i> Saved Events</a></li>
              <li><a class="dropdown-item" href="<?= $appUrl ?>/account"><i class="fas fa-user fa-fw"></i> Profile</a></li>
              <li><a class="dropdown-item" href="<?= $appUrl ?>/account/preferences"><i class="fas fa-bell fa-fw"></i> Email Preferences</a></li>
            </ul>
          </div>
          <form action="<?= $appUrl ?>/logout" method="POST" class="m-0 d-none d-lg-block">
            <?= View::csrf() ?>
            <button type="submit" class="btn-ne btn-ne-ghost btn-ne-sm">
              <i class="fas fa-sign-out-alt"></i>
            </button>
          </form>
        <?php else: ?>
          <a href="<?= $appUrl ?>/login"    class="btn-ne btn-ne-ghost btn-ne-sm d-none d-sm-inline-flex">Sign In</a>
          <a href="<?= $appUrl ?>/register" class="btn-ne btn-ne-primary btn-ne-sm">
            <i class="fas fa-user-plus"></i> <span class="d-none d-sm-inline">Join Free</span><span class="d-sm-none">Join</span>
          </a>
        <?php endif; ?>

        <!-- Mobile hamburger -->
        <button class="ne-menu-toggle d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-label="Open menu" aria-controls="mobileMenu">
          <i class="fas fa-bars"></i>
        </button>
      </div>
    </div>
  </div>
</nav>

<!-- Mobile menu -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="mobileMenu" aria-label="Menu"
     style="border-left:1px solid var(--ne-border);max-width:300px">
  <div class="offcanvas-header" style="border-bottom:1px solid var(--ne-border)">
    <?= View::partial('brand-logo', ['logo_height' => 34]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <nav class="d-flex flex-column gap-1">
      <a href="<?= $appUrl ?>/discover"          class="ne-sidebar-link"><i class="fas fa-compass"></i> Discover Events</a>
      <a href="<?= $appUrl ?>/events/today"      class="ne-sidebar-link"><i class="fas fa-clock"></i> Today</a>
      <a href="<?= $appUrl ?>/events/this-weekend" class="ne-sidebar-link"><i class="fas fa-sun"></i> This Weekend</a>
      <a href="<?= $appUrl ?>/events/free"       class="ne-sidebar-link"><i class="fas fa-gift"></i> Free Events</a>
      <a href="<?= $appUrl ?>/events/online"     class="ne-sidebar-link"><i class="fas fa-laptop"></i> Online Events</a>
      <a href="<?= $appUrl ?>/organizers"        class="ne-sidebar-link"><i class="fas fa-building"></i> Organizers</a>
      <hr class="ne-divider">
      <?php if ($userId): ?>
      <a href="<?= $appUrl ?>/events/create"      class="ne-sidebar-link"><i class="fas fa-plus"></i> Post Event</a>
      <a href="<?= $appUrl ?>/dashboard"          class="ne-sidebar-link"><i class="fas fa-tachometer-alt"></i> My Dashboard</a>
      <a href="<?= $appUrl ?>/my-events"          class="ne-sidebar-link"><i class="fas fa-calendar-check"></i> My Events</a>
      <a href="<?= $appUrl ?>/my-events/saved"    class="ne-sidebar-link"><i class="fas fa-bookmark"></i> Saved Events</a>
      <a href="<?= $appUrl ?>/account"            class="ne-sidebar-link"><i class="fas fa-user"></i> Profile</a>
      <?php else: ?>
      <a href="<?= $appUrl ?>/login"    class="ne-sidebar-link"><i class="fas fa-sign-in-alt"></i> Sign In</a>
      <a href="<?= $appUrl ?>/register" class="ne-sidebar-link"><i class="fas fa-user-plus"></i> Create Account</a>
      <?php endif; ?>
    </nav>
  </div>
</div>
<?php endif; ?>

<!-- ── Main Content ── -->
<main>
  <?php if (str_starts_with($__path, '/admin') || str_starts_with($__path, '/organizer-portal')):
    // Admin & organizer portal: one Brown header band; the work area below
    // stays Cream for dense tables and forms.
    $__isAdmin = str_starts_with($__path, '/admin');
    $__links = $__isAdmin
        ? ['/admin' => 'Dashboard', '/admin/events' => 'Events', '/admin/reports' => 'Reports', '/admin/sources' => 'Sources', '/admin/users' => 'Users', '/admin/districts' => 'Districts']
        : ['/organizer-portal' => 'Dashboard', '/organizer-portal/my-events' => 'My Events', '/organizer-portal/settings' => 'Settings'];
    $__current = '/' . implode('/', array_slice(explode('/', trim($__path, '/')), 0, 2));
  ?>
  <section class="band-dark ne-admin-band">
    <div class="container-fluid" style="max-width:1500px">
      <span class="ne-admin-band-label"><i class="fas <?= $__isAdmin ? 'fa-shield-halved' : 'fa-briefcase' ?> fa-xs"></i> <?= $__isAdmin ? 'Admin Panel' : 'Organizer Portal' ?></span>
      <nav class="ne-admin-band-links" aria-label="<?= $__isAdmin ? 'Admin sections' : 'Organizer portal sections' ?>">
        <?php foreach ($__links as $__href => $__label): ?>
          <a href="<?= $appUrl . $__href ?>"<?= $__current === $__href ? ' class="active" aria-current="page"' : '' ?>><?= $__label ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
  </section>
  <div class="band-cream"><?= $content ?? '' ?></div>
  <?php else: ?>
  <?= $content ?? '' ?>
  <?php endif; ?>
</main>

<?php if (!$__app): ?>
<!-- ── Footer ── -->
<footer class="ne-footer">
  <div class="container-xl">
    <div class="row g-4">
      <div class="col-lg-4">
        <?= View::partial('brand-logo', ['logo_variant' => 'full', 'logo_height' => 112, 'logo_tile' => true]) ?>
        <p class="ne-footer-tagline mt-3 mb-3">Stop searching for events.<br>Let the right events find you.</p>
        <p class="ne-footer-about">Tech meetups, startup nights, workshops, rides and cultural events across India — discovered, verified and posted by the community.</p>
        <div class="ne-social-links">
          <a href="#" class="ne-social-link" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
          <a href="#" class="ne-social-link" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
          <a href="#" class="ne-social-link" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
        </div>
      </div>
      <div class="col-6 col-lg-2">
        <div class="ne-footer-heading">Discover</div>
        <ul class="ne-footer-links">
          <li><a href="<?= $appUrl ?>/events/today">Today's Events</a></li>
          <li><a href="<?= $appUrl ?>/events/this-weekend">This Weekend</a></li>
          <li><a href="<?= $appUrl ?>/events/free">Free Events</a></li>
          <li><a href="<?= $appUrl ?>/events/online">Online Events</a></li>
          <li><a href="<?= $appUrl ?>/discover">All Events</a></li>
        </ul>
      </div>
      <div class="col-6 col-lg-2">
        <div class="ne-footer-heading">Popular Cities</div>
        <ul class="ne-footer-links">
          <li><a href="<?= $appUrl ?>/events/chennai">Chennai</a></li>
          <li><a href="<?= $appUrl ?>/events/bengaluru-urban">Bengaluru</a></li>
          <li><a href="<?= $appUrl ?>/events/mumbai-city">Mumbai</a></li>
          <li><a href="<?= $appUrl ?>/events/hyderabad">Hyderabad</a></li>
          <li><a href="<?= $appUrl ?>/events/new-delhi">Delhi</a></li>
          <li><a href="<?= $appUrl ?>/events/puducherry">Puducherry</a></li>
        </ul>
      </div>
      <div class="col-6 col-lg-2">
        <div class="ne-footer-heading">Platform</div>
        <ul class="ne-footer-links">
          <li><a href="<?= $appUrl ?>/about">About</a></li>
          <li><a href="<?= $appUrl ?>/how-it-works">How It Works</a></li>
          <li><a href="<?= $appUrl ?>/events/create">Post an Event</a></li>
          <li><a href="<?= $appUrl ?>/organizers">Organizer Directory</a></li>
          <li><a href="<?= $appUrl ?>/faq">FAQ</a></li>
        </ul>
      </div>
      <div class="col-6 col-lg-2">
        <div class="ne-footer-heading">Legal</div>
        <ul class="ne-footer-links">
          <li><a href="<?= $appUrl ?>/privacy">Privacy Policy</a></li>
          <li><a href="<?= $appUrl ?>/terms">Terms &amp; Conditions</a></li>
          <li><a href="<?= $appUrl ?>/contact">Contact Us</a></li>
        </ul>
      </div>
    </div>

    <div class="ne-footer-bottom">
      <div class="ne-footer-copy">
        &copy; <?= date('Y') ?> N Events. Proudly built in Tamil Nadu.
      </div>
      <div class="ne-footer-copy">
        Designed with <span style="color:var(--brand-accent)" aria-label="love">♥</span> in Chennai
      </div>
    </div>
  </div>
</footer>
<?php endif; ?>

<?php if ($__tabbar):
  $__isSearch = str_starts_with($__path, '/discover') || str_starts_with($__path, '/search')
             || (str_starts_with($__path, '/events/') && $__path !== '/events/create') || str_starts_with($__path, '/category/');
  $__tabs = [
      ['/',               'fa-compass',             'Explore', $__path === '/'],
      ['/discover',       'fa-magnifying-glass',    'Search',  $__isSearch],
      ['/events/create',  'fa-plus',                'Post',    $__path === '/events/create', true],
      ['/my-events/saved','fa-heart',               'Saved',   $__path === '/my-events/saved'],
      $userId
        ? ['/dashboard',  'fa-user',                'Profile', str_starts_with($__path, '/dashboard') || str_starts_with($__path, '/account') || ($__path === '/my-events' || (str_starts_with($__path, '/my-events/') && $__path !== '/my-events/saved'))]
        : ['/login',      'fa-right-to-bracket',    'Sign in', in_array($__path, ['/login', '/register'], true)],
  ];
?>
<!-- ── Mobile / tablet bottom tab bar (app shell) ── -->
<nav class="ne-tabbar<?= $__app ? '' : ' d-lg-none' ?>" aria-label="Primary">
  <?php foreach ($__tabs as $__t): ?>
    <a href="<?= $appUrl . $__t[0] ?>" class="ne-tab<?= !empty($__t[4]) ? ' ne-tab-post' : '' ?><?= $__t[3] ? ' active' : '' ?>"<?= $__t[3] ? ' aria-current="page"' : '' ?>>
      <i class="fas <?= $__t[1] ?>" aria-hidden="true"></i><span><?= $__t[2] ?></span>
    </a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>

<!-- Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script src="<?= $appUrl ?>/js/nevents.js?v=<?= @filemtime(__DIR__ . '/../../../public/js/nevents.js') ?>"></script>
<?php if (!empty($extra_js)): ?>
<script><?= $extra_js ?></script>
<?php endif; ?>
</body>
</html>
