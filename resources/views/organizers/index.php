<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$app_title = 'Organizers';   // app top bar
ob_start();
?>
<?= View::partial('page-header', ['title' => 'Event <span class="gradient-text">Organizers</span>', 'subtitle' => 'Discover the teams behind the best events across India']) ?>
<section class="ne-section band-cream">
  <div class="container-xl">

    <?php if (empty($organizers)): ?>
      <div style="text-align:center;padding:4rem">
        <p style="color:var(--ne-text-muted)">No organizers listed yet.</p>
      </div>
    <?php else: ?>
      <div class="row g-4 ne-org-grid">
        <?php foreach ($organizers as $org): ?>
          <div class="col-6 col-md-4 col-lg-3 animate-on-scroll">
            <a href="<?= $appUrl ?>/organizer/<?= View::e($org['slug']) ?>"
               style="display:block;background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius);padding:1.5rem;text-align:center;text-decoration:none;transition:all .25s"
               onmouseover="this.style.borderColor='var(--ne-primary)';this.style.transform='translateY(-4px)'"
               onmouseout="this.style.borderColor='var(--ne-border)';this.style.transform='translateY(0)'">
              <?php if (!empty($org['logo_url'])): ?>
                <img src="<?= View::e($org['logo_url']) ?>" alt="<?= View::e($org['name']) ?>"
                     style="width:64px;height:64px;border-radius:50%;object-fit:cover;margin-bottom:1rem">
              <?php else: ?>
                <div style="width:64px;height:64px;border-radius:50%;background:var(--ne-grad-primary);display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:900;color:#fff;margin:0 auto 1rem">
                  <?= strtoupper(substr($org['name'], 0, 1)) ?>
                </div>
              <?php endif; ?>
              <div style="font-weight:700;font-size:.95rem;color:var(--ne-text);margin-bottom:.25rem"><?= View::e($org['name']) ?></div>
              <?php if (!empty($org['event_count'])): ?>
                <div style="font-size:.75rem;color:var(--ne-text-dim)"><?= $org['event_count'] ?> events</div>
              <?php endif; ?>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
