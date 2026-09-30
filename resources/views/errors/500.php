<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$debug  = $debug ?? false;
$ex     = $exception ?? null;
ob_start();
?>
<div class="band-sand" style="min-height:80vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:3rem 1rem">
  <div style="max-width:800px;width:100%">
    <div style="font-size:4rem;margin-bottom:1rem">⚠️</div>
    <h1 style="font-size:1.75rem;font-weight:800;margin-bottom:.75rem">Something Went Wrong</h1>
    <p style="color:var(--ne-text-muted);max-width:400px;margin:0 auto 2rem;line-height:1.7">
      We hit an unexpected error on our end. Please try again in a moment —
      if it keeps happening, let us know.
    </p>

    <?php if ($debug && $ex instanceof \Throwable): ?>
      <pre style="text-align:left;white-space:pre-wrap;word-break:break-word;background:var(--ne-bg-2);color:var(--ne-text-muted);border:1px solid var(--ne-border);padding:1.25rem;border-radius:var(--ne-radius);font-size:.8rem;margin-bottom:2rem"><?= View::e(
        $ex::class . ': ' . $ex->getMessage() . "\n" . $ex->getFile() . ':' . $ex->getLine() . "\n\n" . $ex->getTraceAsString()
      ) ?></pre>
    <?php endif; ?>

    <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
      <a href="<?= $appUrl ?>/" class="btn-ne btn-ne-primary"><i class="fas fa-home fa-xs"></i> Home</a>
      <a href="<?= $appUrl ?>/discover" class="btn-ne btn-ne-secondary"><i class="fas fa-compass fa-xs"></i> Discover Events</a>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
