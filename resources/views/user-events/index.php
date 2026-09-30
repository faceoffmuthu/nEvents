<?php
use NEvents\Core\View;
use NEvents\Services\Events\EventImageService;

$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$labels = [
    'all' => 'All', 'upcoming' => 'Upcoming', 'review' => 'Under review', 'draft' => 'Drafts',
    'completed' => 'Completed', 'cancelled' => 'Cancelled', 'rejected' => 'Rejected / flagged',
];
$all   = array_merge(...array_values($groups));
usort($all, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
$shown = $tab === 'all' ? $all : $groups[$tab];

$pill = [
    'upcoming' => ['published', 'Published'], 'review' => ['review', 'Under review'], 'draft' => ['draft', 'Draft'],
    'completed' => ['completed', 'Completed'], 'cancelled' => ['cancelled', 'Cancelled'], 'rejected' => ['rejected', 'Not published'],
];
ob_start();
?>
<?= View::partial('page-header', ['title' => 'My <span class="gradient-text">Events</span>', 'subtitle' => 'Events you have posted', 'width' => '1000px', 'actions' => '<a href="' . $appUrl . '/events/create" class="btn-ne btn-ne-primary"><i class="fas fa-plus fa-xs"></i> Post Event</a>']) ?>
<div class="band-cream ne-page-body">
  <div class="container-xl" style="max-width:1000px">

    <nav class="ne-tabs" aria-label="Filter events">
      <?php foreach ($labels as $key => $label):
        $count = $key === 'all' ? count($all) : count($groups[$key]);
        if ($key !== 'all' && $count === 0) continue; ?>
        <a href="<?= $appUrl ?>/my-events?tab=<?= $key ?>" class="<?= $tab === $key ? 'active' : '' ?>"><?= $label ?> (<?= $count ?>)</a>
      <?php endforeach; ?>
    </nav>

    <?php if (!$shown): ?>
      <div class="ne-card" style="text-align:center;padding:3.5rem 2rem">
        <div class="ne-empty-icon" aria-hidden="true"><span></span><i class="fas fa-calendar-plus"></i></div>
        <h2 style="font-weight:800;font-size:1.2rem">No events here yet</h2>
        <p style="color:var(--ne-text-muted)">Hosting a meetup, workshop or ride? Post it and it will show up for everyone in your district.</p>
        <a href="<?= $appUrl ?>/events/create" class="btn-ne btn-ne-primary mt-2">Post an Event</a>
      </div>
    <?php endif; ?>

    <?php foreach ($shown as $ev):
      $img = EventImageService::resolve($ev);
      [$pillClass, $pillLabel] = $pill[$ev['group']];
      $when = $ev['next_start']
          ? (new DateTimeImmutable($ev['next_start'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('D, d M Y · h:i A')
          : '—';
      $canEdit   = !in_array($ev['group'], ['cancelled', 'completed'], true) && !in_array($ev['status'], ['archived'], true);
      $canCancel = in_array($ev['status'], ['published', 'pending', 'postponed', 'draft'], true);
      $canDelete = $ev['published_at'] === null && $ev['status'] !== 'published';
    ?>
      <article class="ne-my-event">
        <img src="<?= View::e($img['url']) ?>" alt="<?= View::e($img['alt']) ?>" loading="lazy">
        <div style="flex:1;min-width:0">
          <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin-bottom:.35rem">
            <span class="ne-status-pill ne-status-<?= $pillClass ?>"><?= $pillLabel ?></span>
            <?php if ($ev['moderation_status'] === 'flagged'): ?><span class="ne-status-pill ne-status-review">Flagged</span><?php endif; ?>
            <?php if ((int) $ev['open_reports'] > 0): ?><span class="ne-status-pill ne-status-review"><?= (int) $ev['open_reports'] ?> report(s)</span><?php endif; ?>
          </div>
          <h2 style="font-size:1.05rem;font-weight:800;margin:0 0 .35rem;overflow-wrap:anywhere"><?= View::e($ev['title']) ?></h2>
          <div style="font-size:.82rem;color:var(--ne-text-muted);margin-bottom:.5rem">
            <i class="fas fa-calendar-alt fa-xs"></i> <?= View::e($when) ?>
            &nbsp;·&nbsp; <i class="fas fa-map-marker-alt fa-xs"></i> <?= View::e($ev['format'] === 'online' ? 'Online' : ($ev['district_name'] ?? '—')) ?>
          </div>
          <?php if ($ev['group'] === 'review' && $ev['moderation_reason']): ?>
            <div style="font-size:.78rem;color:var(--ne-gold);margin-bottom:.5rem">Held for review — our team will check it shortly.</div>
          <?php endif; ?>
          <div class="ne-my-event-stats">
            <span><i class="fas fa-eye fa-xs"></i> <?= number_format((int) $ev['view_count']) ?> views</span>
            <span><i class="fas fa-arrow-up-right-from-square fa-xs"></i> <?= number_format((int) $ev['click_count']) ?> registration clicks</span>
            <span><i class="fas fa-bookmark fa-xs"></i> <?= number_format((int) $ev['save_count']) ?> saves</span>
          </div>
          <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.75rem">
            <?php if (in_array($ev['status'], ['published', 'cancelled', 'completed', 'postponed'], true)): ?>
              <a href="<?= $appUrl ?>/event/<?= View::e($ev['slug']) ?>" class="btn-ne btn-ne-ghost btn-ne-sm"><i class="fas fa-eye fa-xs"></i> View</a>
            <?php endif; ?>
            <?php if ($canEdit): ?>
              <a href="<?= $appUrl ?>/my-events/<?= (int) $ev['id'] ?>/edit" class="btn-ne btn-ne-ghost btn-ne-sm"><i class="fas fa-pen fa-xs"></i> Edit</a>
            <?php endif; ?>
            <?php if ($canCancel): ?>
              <form method="POST" action="<?= $appUrl ?>/my-events/<?= (int) $ev['id'] ?>/cancel" class="m-0"
                    onsubmit="return confirm('Cancel this event? It will be removed from upcoming lists and people who saved it will be emailed.')">
                <?= View::csrf() ?>
                <button type="submit" class="btn-ne btn-ne-ghost btn-ne-sm" style="color:var(--ne-accent)"><i class="fas fa-ban fa-xs"></i> Cancel Event</button>
              </form>
            <?php endif; ?>
            <?php if ($canDelete): ?>
              <form method="POST" action="<?= $appUrl ?>/my-events/<?= (int) $ev['id'] ?>/delete" class="m-0"
                    onsubmit="return confirm('Delete this unpublished event permanently?')">
                <?= View::csrf() ?>
                <button type="submit" class="btn-ne btn-ne-ghost btn-ne-sm" style="color:var(--ne-accent)"><i class="fas fa-trash fa-xs"></i> Delete</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
