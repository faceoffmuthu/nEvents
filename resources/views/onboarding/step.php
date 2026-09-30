<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$step   = $step  ?? 1;
$total  = $total ?? 3;
ob_start();
?>
<div class="ne-auth-wrapper <?= \NEvents\Helpers\AppMode::active() ? 'band-cream' : 'band-hero' ?>" style="flex-direction:column;gap:2rem;align-items:center">

  <!-- Progress bar (on a Cream panel: small text can't sit on Orange) -->
  <div class="ne-surface" style="width:100%;max-width:520px;background:var(--bg-surface);border-radius:var(--radius);padding:.85rem 1.1rem;box-shadow:var(--shadow-sm)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
      <span style="font-size:.8rem;color:var(--ne-text-muted)">Step <?= $step ?> of <?= $total ?></span>
      <span style="font-size:.8rem;color:var(--ne-primary)"><?= round($step/$total*100) ?>% complete</span>
    </div>
    <div style="height:4px;background:var(--ne-bg-2);border-radius:2px;overflow:hidden">
      <div style="height:100%;width:<?= round($step/$total*100) ?>%;background:var(--ne-grad-primary);border-radius:2px;transition:width .3s ease"></div>
    </div>
  </div>

  <div class="ne-auth-card" style="max-width:520px">
    <form method="POST" action="<?= $appUrl ?>/onboarding">
      <?= View::csrf() ?>
      <input type="hidden" name="step" value="<?= $step ?>">

      <?php if ($step === 1): ?>
        <h2 style="font-size:1.5rem;font-weight:800;margin-bottom:.5rem">Where are you based? 📍</h2>
        <p style="color:var(--ne-text-muted);margin-bottom:1.5rem">We'll show you events in your district — every state, union territory and district in India is supported.</p>
        <div class="ne-form-group">
          <label class="ne-form-label" for="onb-location">Your city or district</label>
          <?= View::partial('location-picker', ['district' => $district ?? null, 'pickerId' => 'onb-location', 'pickerClass' => 'ne-loc-field']) ?>
        </div>

      <?php elseif ($step === 2): ?>
        <h2 style="font-size:1.5rem;font-weight:800;margin-bottom:.5rem">What interests you? 🎯</h2>
        <p style="color:var(--ne-text-muted);margin-bottom:1.5rem">Pick at least 3 categories and we'll personalise your feed.</p>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:.625rem">
          <?php foreach ($categories ?? [] as $cat): ?>
            <label style="display:flex;align-items:center;gap:.625rem;background:var(--ne-bg-glass);border:1px solid var(--ne-border);border-radius:var(--ne-radius-sm);padding:.75rem;cursor:pointer;transition:all .2s">
              <input type="checkbox" name="category_ids[]" value="<?= $cat['id'] ?>"
                     style="accent-color:var(--ne-primary)">
              <i class="fas <?= View::e($cat['icon'] ?? 'fa-tag') ?> fa-xs" style="color:<?= View::e($cat['color'] ?? 'var(--ne-primary)') ?>"></i>
              <span style="font-size:.875rem;font-weight:500"><?= View::e($cat['name']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>

      <?php elseif ($step === 3): ?>
        <div style="text-align:center">
          <div style="font-size:3rem;margin-bottom:1rem;animation:float 3s ease-in-out infinite">🎉</div>
          <h2 style="font-size:1.75rem;font-weight:800;margin-bottom:.75rem">You're all set!</h2>
          <p style="color:var(--ne-text-muted);margin-bottom:1.5rem">
            Your personalized event feed is ready. Start discovering events tailored just for you.
          </p>
        </div>
      <?php endif; ?>

      <div class="app-save-bar" style="margin-top:1.5rem;display:flex;justify-content:<?= $step > 1 ? 'space-between' : 'flex-end' ?>;gap:.75rem">
        <?php if ($step > 1 && $step < $total): ?>
          <a href="<?= $appUrl ?>/onboarding?step=<?= $step - 1 ?>" class="btn-ne btn-ne-ghost">Back</a>
        <?php endif; ?>
        <button type="submit" class="btn-ne btn-ne-primary" style="min-width:140px">
          <?= $step >= $total ? '<i class="fas fa-rocket fa-xs"></i> Go to Dashboard' : 'Continue <i class="fas fa-arrow-right fa-xs"></i>' ?>
        </button>
      </div>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
