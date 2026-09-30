<?php
/**
 * The one place the N Events logo is rendered on the site.
 *
 *   $logo_variant  'lockup' (default) — symbol + "N Events" name, for bars/headers
 *                  'full'             — the complete official logo (symbol + EVENTS wordmark),
 *                                       only where it's large enough to read (>= ~120px tall)
 *   $logo_height   rendered height in px (lockup default 40, full default 160)
 *   $logo_tile     true = sit the logo on a cream tile (for dark backgrounds — the logo
 *                  itself is never recolored)
 *   $logo_href     link target, or null for no link (default: home page)
 *
 * Assets are generated from the master file by bin/build-brand-assets.ps1.
 * Include it inside a closure or pass variables explicitly — it only reads the
 * $logo_* names below and defines nothing else in the caller's scope except $__logo.
 */
use NEvents\Core\View;

$__logo = [
    'variant' => $logo_variant ?? 'lockup',
    'tile'    => !empty($logo_tile),
    'href'    => array_key_exists('logo_href', get_defined_vars()) ? $logo_href : View::url(''),
];
$__logo['height'] = (int) ($logo_height ?? ($__logo['variant'] === 'full' ? 160 : 40));

if ($__logo['variant'] === 'full') {
    // n-events-logo.png is 495x512 (aspect 0.967)
    $__logo['html'] = sprintf(
        '<img src="%s" alt="N Events" width="%d" height="%d" class="ne-logo-full" decoding="async">',
        View::e(View::asset('images/brand/n-events-logo.png')),
        (int) round($__logo['height'] * 495 / 512), $__logo['height']
    );
} else {
    // n-events-mark.png is 266x256; the name is live text so it stays sharp and readable
    $__logo['html'] = sprintf(
        '<img src="%s" alt="" width="%d" height="%d" class="ne-logo-mark" decoding="async"><span class="ne-logo-name">N Events</span>',
        View::e(View::asset('images/brand/n-events-mark.png')),
        (int) round($__logo['height'] * 266 / 256), $__logo['height']
    );
}

$__logo['class'] = 'ne-logo ne-logo-' . $__logo['variant'] . ($__logo['tile'] ? ' ne-logo-tile' : '');
$__logo['style'] = '--logo-h:' . $__logo['height'] . 'px';

if ($__logo['href'] !== null): ?>
<a href="<?= View::e($__logo['href']) ?>" class="<?= $__logo['class'] ?>" style="<?= $__logo['style'] ?>" aria-label="N Events — home"><?= $__logo['html'] ?></a>
<?php else: ?>
<span class="<?= $__logo['class'] ?>" style="<?= $__logo['style'] ?>" role="img" aria-label="N Events"><?= $__logo['html'] ?></span>
<?php endif; unset($__logo); ?>
