<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
$p      = $prefs ?? [];
$digestTime = substr((string) ($p['digest_time'] ?? '08:00:00'), 0, 5);
ob_start();
?>
<?= View::partial('page-header', ['title' => 'Notification <span class="gradient-text">Preferences</span>', 'subtitle' => 'All notifications are sent by email. Use "Add to Google Calendar" on any event page to put it in your calendar.', 'width' => '640px']) ?>
<div class="band-cream ne-page-body">
  <div class="container-xl" style="max-width:640px">

    <form method="POST" action="<?= $appUrl ?>/account/preferences">
      <?= View::csrf() ?>

      <?php
      $groups = [
          'Email' => [
              ['email_enabled', 'Email notifications enabled', 'Turn off to stop all digests, reminders and updates (account emails still arrive).'],
          ],
          'Digests' => [
              ['daily_digest',  'Daily event digest',  'Upcoming events in your district that match your interests.'],
              ['weekly_digest', 'Weekly event digest', 'A once-a-week roundup, sent on Mondays.'],
          ],
          'Saved events' => [
              ['reminder_24h',  'Reminder the day before',  'Also covers "registration closing soon" for events you saved.'],
              ['reminder_2h',   'Reminder shortly before it starts', 'About 2 hours before the start time.'],
              ['event_updates', 'Event update notifications', 'When a saved event is updated, postponed or cancelled.'],
          ],
      ];
      foreach ($groups as $groupTitle => $items):
      ?>
        <div style="background:var(--ne-bg-card);border:1px solid var(--ne-border);border-radius:var(--ne-radius);padding:1.5rem;margin-bottom:1rem">
          <h2 style="font-size:1rem;font-weight:700;margin-bottom:.5rem"><?= View::e($groupTitle) ?></h2>
          <?php foreach ($items as [$key, $label, $hint]): ?>
            <label style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.625rem 0;border-bottom:1px solid var(--ne-border)">
              <span>
                <span style="font-size:.9rem;display:block"><?= View::e($label) ?></span>
                <span style="font-size:.75rem;color:var(--ne-text-dim)"><?= View::e($hint) ?></span>
              </span>
              <input type="checkbox" class="ne-switch" name="<?= $key ?>" value="1"
                     <?= !empty($p[$key]) ? 'checked' : '' ?>
                     style="width:18px;height:18px;accent-color:var(--ne-primary);flex-shrink:0">
            </label>
          <?php endforeach; ?>
          <?php if ($groupTitle === 'Digests'): ?>
            <label style="display:flex;align-items:center;justify-content:space-between;padding:.625rem 0">
              <span style="font-size:.9rem">Preferred digest time (IST)</span>
              <input type="time" name="digest_time" value="<?= View::e($digestTime) ?>" class="ne-form-control" style="max-width:140px">
            </label>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <div class="app-save-bar">
        <button type="submit" class="btn-ne btn-ne-primary">
          <i class="fas fa-save fa-xs"></i> Save Preferences
        </button>
      </div>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
