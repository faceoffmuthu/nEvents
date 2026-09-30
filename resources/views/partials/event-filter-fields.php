<?php
use NEvents\Core\View;
/**
 * Shared filter fields, included inside both the desktop sidebar form and
 * the mobile offcanvas form so district/category/format/price/date filtering
 * is identical on every breakpoint (single source of truth).
 * Expected variables: $filters, $states, $districts (the chosen state's), $categories, $appUrl
 * Optional: $autoSubmit (default true) — false inside a sheet with its own "Show events" button.
 */
$onchange = ($autoSubmit ?? true) ? ' onchange="this.form.submit()"' : '';
?>
<div class="ne-filter-title">State / union territory</div>
<select name="state" class="ne-form-control ne-form-control-sm mb-2"<?= $onchange ?> style="font-size:.8rem;padding:.5rem .75rem" aria-label="Filter by state or union territory" data-state-select>
  <option value="">All India</option>
  <?php foreach ($states ?? [] as $st): ?>
    <option value="<?= (int) $st['id'] ?>" <?= (int) ($filters['state_id'] ?? 0) === (int) $st['id'] ? 'selected' : '' ?>><?= View::e($st['name']) ?></option>
  <?php endforeach; ?>
</select>
<div class="ne-filter-title">District</div>
<select name="district" class="ne-form-control ne-form-control-sm mb-3"<?= $onchange ?> style="font-size:.8rem;padding:.5rem .75rem" aria-label="Filter by district" data-district-select<?= empty($filters['state_id']) ? ' disabled' : '' ?>>
  <option value="">All districts</option>
  <?php foreach ($districts ?? [] as $district): ?>
    <option value="<?= $district['id'] ?>" <?= ($filters['district_id'] ?? '') == $district['id'] ? 'selected' : '' ?>>
      <?= View::e($district['name']) ?>
    </option>
  <?php endforeach; ?>
</select>

<div class="ne-filter-title">Category</div>
<?php foreach ($categories ?? [] as $cat): ?>
  <label class="ne-filter-option">
    <input type="radio" name="category" value="<?= $cat['id'] ?>"
           <?= ($filters['category_id'] ?? '') == $cat['id'] ? 'checked' : '' ?>
          <?= $onchange ?>>
    <i class="fas <?= View::e($cat['icon'] ?? 'fa-tag') ?> fa-xs" style="color:<?= View::e($cat['color'] ?? 'var(--ne-primary)') ?>"></i>
    <?= View::e($cat['name']) ?>
  </label>
<?php endforeach; ?>

<hr class="ne-divider">

<div class="ne-filter-title">Format</div>
<?php foreach (['offline' => 'In Person', 'online' => 'Online', 'hybrid' => 'Hybrid'] as $val => $label): ?>
  <label class="ne-filter-option">
    <input type="radio" name="format" value="<?= $val ?>"
           <?= ($filters['format'] ?? '') === $val ? 'checked' : '' ?>
          <?= $onchange ?>>
    <?= $label ?>
  </label>
<?php endforeach; ?>

<hr class="ne-divider">

<div class="ne-filter-title">Price</div>
<label class="ne-filter-option">
  <input type="checkbox" name="free" value="1"
         <?= !empty($filters['free']) ? 'checked' : '' ?>
        <?= $onchange ?>>
  Free events only
</label>

<hr class="ne-divider">

<div class="ne-filter-title">Date</div>
<div style="display:flex;flex-direction:column;gap:.5rem">
  <input type="date" name="from" value="<?= View::e(substr((string) ($filters['date_from'] ?? ''), 0, 10)) ?>"
         class="ne-form-control" style="font-size:.8rem;padding:.5rem .75rem"<?= $onchange ?>>
  <input type="date" name="to" value="<?= View::e(substr((string) ($filters['date_to'] ?? ''), 0, 10)) ?>"
         class="ne-form-control" style="font-size:.8rem;padding:.5rem .75rem"<?= $onchange ?>>
</div>

<?php if (!empty(array_filter($filters))): ?>
  <a href="<?= $appUrl ?>/discover" class="btn-ne btn-ne-ghost btn-ne-sm w-100 mt-3 justify-content-center">
    <i class="fas fa-times-circle fa-xs"></i> Clear Filters
  </a>
<?php endif; ?>
