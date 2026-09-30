<?php
/**
 * Green page-header band (Section 1 of the Green / Cream pattern).
 * The page content that follows goes in a .band-cream block.
 *
 *   $title     heading HTML (trusted — may contain <span class="gradient-text">)
 *   $subtitle  plain text, optional
 *   $actions   HTML placed at the right (buttons/tabs), optional
 *   $center    true = centered heading
 *   $width     container max-width, e.g. '800px' (default: full container)
 */
?>
<section class="band-sand ne-page-header<?= !empty($center) ? ' is-center' : '' ?>">
  <div class="container-xl"<?= !empty($width) ? ' style="max-width:' . htmlspecialchars((string) $width, ENT_QUOTES) . '"' : '' ?>>
    <div class="ne-page-header-row">
      <div>
        <h1 class="ne-page-title"><?= $title ?? '' ?></h1>
        <?php if (!empty($subtitle)): ?><p class="ne-page-subtitle"><?= htmlspecialchars((string) $subtitle, ENT_QUOTES) ?></p><?php endif; ?>
      </div>
      <?php if (!empty($actions)): ?><div class="ne-page-header-actions"><?= $actions ?></div><?php endif; ?>
    </div>
  </div>
</section>
