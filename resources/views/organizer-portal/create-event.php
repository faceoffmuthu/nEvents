<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
ob_start();
?>
<section class="ne-section">
  <div class="container-xl" style="max-width:800px">
    <h1 class="ne-section-title animate-on-scroll">Submit an <span class="gradient-text">Event</span></h1>

    <div class="ne-alert ne-alert-info mb-4">
      <i class="fas fa-info-circle"></i>
      <span>N Events is a discovery platform — not a ticketing system. You must have your own registration page. We link directly to it.</span>
    </div>

    <form method="POST" action="<?= $appUrl ?>/organizer-portal/submit" enctype="multipart/form-data">
      <?= View::csrf() ?>

      <div class="row g-3">
        <div class="col-12">
          <div class="ne-form-group">
            <label class="ne-form-label">Event Title *</label>
            <input type="text" name="title" class="ne-form-control" required maxlength="200"
                   placeholder="e.g. Chennai React Meetup — October 2025">
          </div>
        </div>

        <div class="col-md-6">
          <div class="ne-form-group">
            <label class="ne-form-label">Format *</label>
            <select name="format" class="ne-form-control" required>
              <option value="offline">In Person</option>
              <option value="online">Online</option>
              <option value="hybrid">Hybrid</option>
            </select>
          </div>
        </div>

        <div class="col-md-6">
          <div class="ne-form-group">
            <label class="ne-form-label">Event Date *</label>
            <input type="datetime-local" name="start_at" class="ne-form-control" required>
          </div>
        </div>

        <div class="col-md-6">
          <div class="ne-form-group">
            <label class="ne-form-label">End Date/Time</label>
            <input type="datetime-local" name="end_at" class="ne-form-control">
          </div>
        </div>

        <div class="col-md-6">
          <div class="ne-form-group">
            <label class="ne-form-label">Is this a free event?</label>
            <select name="is_free" class="ne-form-control">
              <option value="1">Free</option>
              <option value="0">Paid</option>
            </select>
          </div>
        </div>

        <div class="col-12">
          <div class="ne-form-group">
            <label class="ne-form-label">Registration URL *</label>
            <input type="url" name="registration_url" class="ne-form-control" required
                   placeholder="https://your-registration-page.com">
            <div style="font-size:.75rem;color:var(--ne-text-dim);margin-top:.35rem">
              The URL where attendees register. Must be http/https and publicly accessible.
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="ne-form-group">
            <label class="ne-form-label">Description *</label>
            <textarea name="description" class="ne-form-control" rows="6" required
                      placeholder="Describe what attendees can expect…"></textarea>
          </div>
        </div>

        <div class="col-12">
          <button type="submit" class="btn-ne btn-ne-primary">
            <i class="fas fa-paper-plane fa-xs"></i> Submit for Review
          </button>
        </div>
      </div>
    </form>
  </div>
</section>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
