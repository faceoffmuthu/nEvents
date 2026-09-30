<?php
use NEvents\Core\View;
$appUrl   = rtrim($_ENV['APP_URL'] ?? '', '/');
$selected = $selected ?? [];
ob_start();
?>
<?= View::partial('page-header', ['title' => 'Your <span class="gradient-text">interests</span>', 'subtitle' => 'Pick topics you care about — they power the "For you" row on the home page.', 'width' => '860px']) ?>
<div class="band-cream ne-page-body">
  <div class="container-xl" style="max-width:860px">
    <form method="POST" action="<?= $appUrl ?>/account/interests" class="ne-interests-form">
      <?= View::csrf() ?>
      <?php foreach ($categories ?? [] as $cat): ?>
        <fieldset class="ne-interest-group">
          <legend>
            <i class="fas <?= View::e($cat['icon'] ?? 'fa-tag') ?>" style="color:<?= View::e($cat['color'] ?? 'var(--brand-primary)') ?>" aria-hidden="true"></i>
            <?= View::e($cat['name']) ?>
          </legend>
          <div class="ne-interest-chips">
            <?php foreach (array_merge([$cat], $cat['children'] ?? []) as $i => $c): ?>
              <label class="ne-interest">
                <input type="checkbox" name="categories[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $selected, true) ? 'checked' : '' ?>>
                <span><?= $i === 0 ? 'All ' . View::e($c['name']) : View::e($c['name']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
      <?php endforeach; ?>

      <div class="ne-form-actions app-save-bar">
        <a href="<?= $appUrl ?>/dashboard" class="btn-ne btn-ne-ghost">Cancel</a>
        <button type="submit" class="btn-ne btn-ne-primary">Save interests</button>
      </div>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
