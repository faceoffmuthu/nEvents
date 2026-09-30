<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
ob_start();
?>
<div class="band-sand" style="min-height:80vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:3rem 1rem">
  <div>
    <div style="font-size:8rem;font-weight:900;background:var(--ne-grad-primary);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;line-height:1;margin-bottom:1rem;font-family:var(--ne-font-display)">
      404
    </div>
    <h1 style="font-size:1.75rem;font-weight:800;margin-bottom:.75rem">Page Not Found</h1>
    <p style="color:var(--ne-text-muted);max-width:400px;margin:0 auto 2.5rem;line-height:1.7">
      The page you're looking for has moved, expired, or never existed.
      Let's get you back to discovering events.
    </p>
    <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
      <a href="<?= $appUrl ?>/"        class="btn-ne btn-ne-primary"><i class="fas fa-home fa-xs"></i> Home</a>
      <a href="<?= $appUrl ?>/discover" class="btn-ne btn-ne-secondary"><i class="fas fa-compass fa-xs"></i> Discover Events</a>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
