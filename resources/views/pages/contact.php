<?php
use NEvents\Core\View;
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
ob_start();
?>
<?= View::partial('page-header', ['title' => 'Contact <span class="gradient-text">Us</span>', 'subtitle' => 'Questions, suggestions or events to list — we\'d love to hear from you', 'width' => '640px']) ?>
<section class="ne-section band-cream">
  <div class="container-xl" style="max-width:640px">

    <div class="ne-auth-card">
      <p style="color:var(--ne-text-muted);margin-bottom:1.5rem">
        Have a question, suggestion, or want to list your events? We'd love to hear from you.
      </p>

      <div style="display:flex;flex-direction:column;gap:1rem;margin-bottom:1.5rem">
        <div style="display:flex;align-items:center;gap:1rem;background:var(--ne-bg-glass);border:1px solid var(--ne-border);border-radius:var(--ne-radius-sm);padding:1rem">
          <i class="fas fa-envelope fa-lg" style="color:var(--ne-primary);width:24px;text-align:center"></i>
          <div>
            <div style="font-size:.75rem;color:var(--ne-text-dim);text-transform:uppercase;letter-spacing:.05em">General Enquiries</div>
            <div style="font-weight:600">hello@nevents.in</div>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:1rem;background:var(--ne-bg-glass);border:1px solid var(--ne-border);border-radius:var(--ne-radius-sm);padding:1rem">
          <i class="fas fa-building fa-lg" style="color:var(--ne-secondary);width:24px;text-align:center"></i>
          <div>
            <div style="font-size:.75rem;color:var(--ne-text-dim);text-transform:uppercase;letter-spacing:.05em">For Organizers</div>
            <div style="font-weight:600">organizers@nevents.in</div>
          </div>
        </div>
      </div>

      <a href="<?= $appUrl ?>/submit-event" class="btn-ne btn-ne-primary w-100 justify-content-center">
        <i class="fas fa-plus fa-xs"></i> Submit Your Event
      </a>
    </div>
  </div>
</section>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
