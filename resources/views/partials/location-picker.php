<?php
use NEvents\Core\View;
/**
 * Location picker for anywhere in India: type a city, district or state and pick
 * from the list (nevents.js initLocationPickers, results from /api/locations).
 * The chosen district's id goes in a hidden field.
 *
 * Variables (all optional):
 *   $name        hidden field name (default "district_id")
 *   $district    the selected district row (name, state_name) or null
 *   $pickerId    id of the text field (a label's "for")
 *   $allowAll    offer "All India" (an empty value)
 *   $autoSave    save the choice as the visitor's location, then reload the page
 *   $pickerClass extra classes on the wrapper
 *   $invalid     mark the field invalid
 *   $ariaLabel   accessible name when there is no visible label
 */
$pickerId = $pickerId ?? 'loc-' . bin2hex(random_bytes(3));
$district = $district ?? null;
$current  = $district ? $district['name'] . ', ' . $district['state_name'] : (!empty($allowAll) ? 'All India' : '');
?>
<div class="ne-loc <?= View::e($pickerClass ?? '') ?>" data-loc-picker<?= !empty($allowAll) ? ' data-allow-all' : '' ?><?= !empty($autoSave) ? ' data-autosave' : '' ?>>
  <i class="fas fa-location-dot ne-loc-icon" aria-hidden="true"></i>
  <input type="text" id="<?= View::e($pickerId) ?>" class="ne-loc-input<?= !empty($invalid) ? ' is-invalid' : '' ?>"
         value="<?= View::e($current) ?>" data-current="<?= View::e($current) ?>"
         placeholder="Type a city, district or state" autocomplete="off" spellcheck="false"
         role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="<?= View::e($pickerId) ?>-list"
         <?= !empty($ariaLabel) ? 'aria-label="' . View::e($ariaLabel) . '"' : '' ?>>
  <input type="hidden" name="<?= View::e($name ?? 'district_id') ?>" value="<?= $district ? (int) $district['id'] : '' ?>">
  <ul id="<?= View::e($pickerId) ?>-list" class="ne-loc-list" role="listbox" hidden></ul>
</div>
