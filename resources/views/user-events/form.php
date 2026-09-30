<?php
use NEvents\Core\View;
use NEvents\Services\Events\EventImageService;

$appUrl  = rtrim($_ENV['APP_URL'] ?? '', '/');
$errors  = $errors ?? [];
$old     = $old ?? [];
$isEdit  = ($mode ?? 'create') === 'edit';
$action  = $isEdit ? $appUrl . '/my-events/' . (int) $event['id'] . '/edit' : $appUrl . '/events/create';
$v       = fn(string $k, $default = '') => View::e($old[$k] ?? $default);
$sel     = fn(string $k, $val) => (string) ($old[$k] ?? '') === (string) $val ? 'selected' : '';
$chk     = fn(string $k, $val) => (string) ($old[$k] ?? '') === (string) $val ? 'checked' : '';
$inv     = fn(string $k) => isset($errors[$k]) ? 'is-invalid' : '';
$err     = function (string $k) use ($errors) {
    return isset($errors[$k])
        ? '<div class="ne-form-error"><i class="fas fa-exclamation-circle fa-xs"></i>' . View::e($errors[$k]) . '</div>'
        : '';
};
$extraCats = array_map('strval', (array) ($old['additional_category_ids'] ?? []));
$maxMb     = (int) \NEvents\Core\Application::getInstance()->config('app.user_events.image_max_mb', 5);
$currentImage = !empty($old['featured_image_url']) ? EventImageService::resolve(['featured_image_url' => $old['featured_image_url']])['url'] : '';

$sections = [
    'basic'    => ['Basic Information', ['title', 'short_summary', 'description', 'primary_category_id', 'additional_category_ids', 'tags', 'format']],
    'datetime' => ['Date & Time', ['start_date', 'start_time', 'end_date', 'end_time', 'timezone']],
    'location' => ['Location', ['district_id', 'city_area', 'venue_name', 'address', 'postal_code', 'latitude', 'map_url', 'online_platform', 'online_url']],
    'register' => ['Registration & Pricing', ['registration_url', 'registration_deadline', 'min_price', 'max_price']],
    'organizer'=> ['Organizer', ['organizer_name', 'organizer_email', 'organizer_phone', 'organizer_website', 'organizer_social_url']],
    'image'    => ['Event Image', ['image']],
];
$timezones = array_unique(array_merge([$old['timezone'] ?? 'Asia/Kolkata'], ['Asia/Kolkata', 'Asia/Dubai', 'Asia/Singapore', 'Europe/London', 'America/New_York', 'UTC']));

ob_start();
?>
<?= View::partial('page-header', ['title' => ($isEdit ? 'Edit' : 'Post an') . ' <span class="gradient-text">Event</span>', 'subtitle' => $isEdit ? 'Changes are re-checked automatically. People who saved this event are emailed about date, venue or link changes.' : 'Events that pass our automatic checks are published right away and appear in event lists for everyone in the district.']) ?>
<div class="band-cream ne-page-body">
  <div class="container-xl">

    <?php if (!empty($errors['_general'])): ?>
      <div class="ne-alert ne-alert-error mb-3"><i class="fas fa-exclamation-circle"></i> <?= View::e($errors['_general']) ?></div>
    <?php elseif ($errors && empty($duplicate)): ?>
      <div class="ne-alert ne-alert-error mb-3"><i class="fas fa-exclamation-circle"></i> Please fix the highlighted fields below.</div>
    <?php endif; ?>

    <?php if (!empty($duplicate)): ?>
      <div class="ne-alert ne-alert-warning mb-3" style="display:block">
        <strong><i class="fas fa-clone"></i> <?= View::e($errors['_duplicate'] ?? 'Possible duplicate') ?></strong>
        <div style="margin-top:.35rem;color:var(--ne-text-muted)">
          We found <a href="<?= $appUrl ?>/event/<?= View::e($duplicate['slug']) ?>" target="_blank" rel="noopener" style="color:var(--ne-primary);font-weight:600"><?= View::e($duplicate['title']) ?></a>
          on the same day. If it's the same event, there's no need to post it again — save that one instead.
        </div>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= $action ?>" enctype="multipart/form-data" id="eventForm" novalidate data-loading data-wizard>
      <!-- Step header: shown on phones/tablets when the form runs as a step-by-step wizard -->
      <div class="ne-wizard-head" hidden aria-live="polite">
        <div class="ne-wizard-count"><span data-wz-step>1</span> of <?= count($sections) ?> · <span data-wz-title><?= View::e(array_values($sections)[0][0]) ?></span></div>
        <div class="ne-wizard-progress" role="progressbar" aria-valuemin="1" aria-valuemax="<?= count($sections) ?>" aria-valuenow="1"><span data-wz-bar></span></div>
      </div>
      <?= View::csrf() ?>
      <input type="hidden" name="MAX_FILE_SIZE" value="<?= $maxMb * 1024 * 1024 ?>">

      <div class="row g-4">
        <div class="col-lg-8">

          <!-- 1. BASIC -->
          <section class="ne-form-section" id="sec-basic">
            <h2 class="ne-form-section-title"><span class="ne-form-section-num">1</span> Basic Information</h2>
            <p class="ne-form-section-desc">What is the event and who is it for? Plain text only.</p>

            <div class="ne-form-group">
              <label class="ne-form-label" for="f-title">Event Title *</label>
              <input id="f-title" type="text" name="title" maxlength="150" required class="ne-form-control <?= $inv('title') ?>"
                     value="<?= $v('title') ?>" placeholder="e.g. Chennai AI Builders Meetup — October Edition" data-preview="title">
              <?= $err('title') ?>
            </div>
            <div class="ne-form-group">
              <label class="ne-form-label" for="f-summary">Short Summary *</label>
              <input id="f-summary" type="text" name="short_summary" maxlength="300" required class="ne-form-control <?= $inv('short_summary') ?>"
                     value="<?= $v('short_summary') ?>" placeholder="One or two lines shown on event cards (20–300 characters)">
              <?= $err('short_summary') ?>
            </div>
            <div class="ne-form-group">
              <label class="ne-form-label" for="f-desc">Detailed Description *</label>
              <textarea id="f-desc" name="description" rows="7" maxlength="5000" required class="ne-form-control <?= $inv('description') ?>"
                        placeholder="Agenda, speakers, who should attend, what to bring…"><?= $v('description') ?></textarea>
              <div class="ne-form-hint">50–5000 characters. Line breaks are kept; HTML is not allowed.</div>
              <?= $err('description') ?>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <div class="ne-form-group">
                  <label class="ne-form-label" for="f-cat">Primary Category *</label>
                  <select id="f-cat" name="primary_category_id" required class="ne-form-control <?= $inv('primary_category_id') ?>" data-preview="category">
                    <option value="">Choose…</option>
                    <?php foreach ($categories as $top): ?>
                      <optgroup label="<?= View::e($top['name']) ?>">
                        <option value="<?= (int) $top['id'] ?>" <?= $sel('primary_category_id', $top['id']) ?>><?= View::e($top['name']) ?></option>
                        <?php foreach ($top['children'] as $child): ?>
                          <option value="<?= (int) $child['id'] ?>" <?= $sel('primary_category_id', $child['id']) ?>>— <?= View::e($child['name']) ?></option>
                        <?php endforeach; ?>
                      </optgroup>
                    <?php endforeach; ?>
                  </select>
                  <?= $err('primary_category_id') ?>
                </div>
              </div>
              <div class="col-md-6">
                <div class="ne-form-group">
                  <label class="ne-form-label" for="f-cats">Additional Categories <span style="color:var(--ne-text-dim);font-weight:400">(up to 3)</span></label>
                  <select id="f-cats" name="additional_category_ids[]" multiple size="4" class="ne-form-control <?= $inv('additional_category_ids') ?>">
                    <?php foreach ($categories as $top): ?>
                      <optgroup label="<?= View::e($top['name']) ?>">
                        <option value="<?= (int) $top['id'] ?>" <?= in_array((string) $top['id'], $extraCats, true) ? 'selected' : '' ?>><?= View::e($top['name']) ?></option>
                        <?php foreach ($top['children'] as $child): ?>
                          <option value="<?= (int) $child['id'] ?>" <?= in_array((string) $child['id'], $extraCats, true) ? 'selected' : '' ?>><?= View::e($child['name']) ?></option>
                        <?php endforeach; ?>
                      </optgroup>
                    <?php endforeach; ?>
                  </select>
                  <div class="ne-form-hint">Ctrl/Cmd-click to select several.</div>
                  <?= $err('additional_category_ids') ?>
                </div>
              </div>
              <div class="col-md-8">
                <div class="ne-form-group">
                  <label class="ne-form-label" for="f-tags">Tags</label>
                  <input id="f-tags" type="text" name="tags" class="ne-form-control <?= $inv('tags') ?>" value="<?= $v('tags') ?>" placeholder="genai, python, networking">
                  <?= $err('tags') ?>
                </div>
              </div>
              <div class="col-md-4">
                <div class="ne-form-group">
                  <label class="ne-form-label" for="f-lang">Event Language</label>
                  <select id="f-lang" name="primary_language" class="ne-form-control">
                    <?php foreach ($languages as $code => $label): ?>
                      <option value="<?= $code ?>" <?= $sel('primary_language', $code) ?>><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <label class="ne-form-label">Event Format *</label>
            <div class="ne-choice-row" role="radiogroup">
              <?php foreach (['offline' => ['fa-map-marker-alt', 'Offline'], 'online' => ['fa-laptop', 'Online'], 'hybrid' => ['fa-people-arrows', 'Hybrid']] as $val => [$icon, $label]): ?>
                <label class="ne-choice"><input type="radio" name="format" value="<?= $val ?>" <?= $chk('format', $val) ?> data-preview="format"><span><i class="fas <?= $icon ?> fa-xs"></i> <?= $label ?></span></label>
              <?php endforeach; ?>
            </div>
            <?= $err('format') ?>
          </section>

          <!-- 2. DATE & TIME -->
          <section class="ne-form-section" id="sec-datetime">
            <h2 class="ne-form-section-title"><span class="ne-form-section-num">2</span> Date &amp; Time</h2>
            <p class="ne-form-section-desc">Only upcoming or ongoing events can be posted.</p>
            <div class="row g-3">
              <div class="col-6 col-md-3"><div class="ne-form-group">
                <label class="ne-form-label" for="f-sd">Start Date *</label>
                <input id="f-sd" type="date" name="start_date" required class="ne-form-control <?= $inv('start_date') ?>" value="<?= $v('start_date') ?>" data-preview="date">
              </div></div>
              <div class="col-6 col-md-3"><div class="ne-form-group">
                <label class="ne-form-label" for="f-st">Start Time *</label>
                <input id="f-st" type="time" name="start_time" required class="ne-form-control <?= $inv('start_date') ?>" value="<?= $v('start_time') ?>" data-preview="date">
              </div></div>
              <div class="col-6 col-md-3"><div class="ne-form-group">
                <label class="ne-form-label" for="f-ed">End Date *</label>
                <input id="f-ed" type="date" name="end_date" required class="ne-form-control <?= $inv('end_date') ?>" value="<?= $v('end_date') ?>">
              </div></div>
              <div class="col-6 col-md-3"><div class="ne-form-group">
                <label class="ne-form-label" for="f-et">End Time *</label>
                <input id="f-et" type="time" name="end_time" required class="ne-form-control <?= $inv('end_date') ?>" value="<?= $v('end_time') ?>">
              </div></div>
            </div>
            <?= $err('start_date') ?><?= $err('end_date') ?>
            <div class="ne-form-group" style="max-width:320px">
              <label class="ne-form-label" for="f-tz">Timezone *</label>
              <select id="f-tz" name="timezone" class="ne-form-control <?= $inv('timezone') ?>">
                <?php foreach ($timezones as $tz): ?>
                  <option value="<?= View::e($tz) ?>" <?= $sel('timezone', $tz) ?>><?= View::e($tz === 'Asia/Kolkata' ? 'Asia/Kolkata (IST)' : $tz) ?></option>
                <?php endforeach; ?>
              </select>
              <?= $err('timezone') ?>
            </div>
          </section>

          <!-- 3. LOCATION -->
          <section class="ne-form-section" id="sec-location">
            <h2 class="ne-form-section-title"><span class="ne-form-section-num">3</span> Location</h2>
            <p class="ne-form-section-desc">Anywhere in India. The district decides which district's event list the event appears in.</p>

            <div data-show-for="offline hybrid">
              <div class="row g-3">
                <div class="col-md-6"><div class="ne-form-group">
                  <label class="ne-form-label" for="f-district">District *</label>
                  <?= View::partial('location-picker', ['district' => $district ?? null, 'pickerId' => 'f-district',
                        'pickerClass' => 'ne-loc-field', 'invalid' => $inv('district_id') !== '']) ?>
                  <?= $err('district_id') ?>
                </div></div>
                <div class="col-md-6"><div class="ne-form-group">
                  <label class="ne-form-label" for="f-area">City / Area *</label>
                  <input id="f-area" type="text" name="city_area" maxlength="100" class="ne-form-control <?= $inv('city_area') ?>" value="<?= $v('city_area') ?>" placeholder="e.g. Guindy" data-preview="area">
                  <?= $err('city_area') ?>
                </div></div>
                <div class="col-md-6"><div class="ne-form-group">
                  <label class="ne-form-label" for="f-venue">Venue Name *</label>
                  <input id="f-venue" type="text" name="venue_name" maxlength="200" class="ne-form-control <?= $inv('venue_name') ?>" value="<?= $v('venue_name') ?>" placeholder="e.g. IIT Madras Research Park" data-preview="venue">
                  <?= $err('venue_name') ?>
                </div></div>
                <div class="col-md-6"><div class="ne-form-group">
                  <label class="ne-form-label" for="f-pin">Postal Code</label>
                  <input id="f-pin" type="text" name="postal_code" inputmode="numeric" maxlength="6" class="ne-form-control <?= $inv('postal_code') ?>" value="<?= $v('postal_code') ?>" placeholder="600036">
                  <?= $err('postal_code') ?>
                </div></div>
                <div class="col-12"><div class="ne-form-group">
                  <label class="ne-form-label" for="f-address">Address *</label>
                  <textarea id="f-address" name="address" rows="2" maxlength="500" class="ne-form-control <?= $inv('address') ?>"><?= $v('address') ?></textarea>
                  <?= $err('address') ?>
                </div></div>
                <div class="col-md-3"><div class="ne-form-group">
                  <label class="ne-form-label" for="f-lat">Latitude</label>
                  <input id="f-lat" type="text" name="latitude" inputmode="decimal" class="ne-form-control <?= $inv('latitude') ?>" value="<?= $v('latitude') ?>" placeholder="12.99">
                </div></div>
                <div class="col-md-3"><div class="ne-form-group">
                  <label class="ne-form-label" for="f-lng">Longitude</label>
                  <input id="f-lng" type="text" name="longitude" inputmode="decimal" class="ne-form-control <?= $inv('latitude') ?>" value="<?= $v('longitude') ?>" placeholder="80.24">
                </div></div>
                <div class="col-md-6"><div class="ne-form-group">
                  <label class="ne-form-label" for="f-map">Google Maps URL</label>
                  <input id="f-map" type="url" name="map_url" class="ne-form-control <?= $inv('map_url') ?>" value="<?= $v('map_url') ?>" placeholder="https://maps.google.com/…">
                  <?= $err('map_url') ?>
                </div></div>
              </div>
              <?= $err('latitude') ?>
            </div>

            <div data-show-for="online hybrid">
              <div class="row g-3">
                <div class="col-md-5"><div class="ne-form-group">
                  <label class="ne-form-label" for="f-platform">Online Platform</label>
                  <input id="f-platform" type="text" name="online_platform" maxlength="100" class="ne-form-control <?= $inv('online_platform') ?>" value="<?= $v('online_platform') ?>" placeholder="Zoom, Google Meet, YouTube Live…">
                  <?= $err('online_platform') ?>
                </div></div>
                <div class="col-md-7"><div class="ne-form-group">
                  <label class="ne-form-label" for="f-onlineurl">Public Information URL</label>
                  <input id="f-onlineurl" type="url" name="online_url" class="ne-form-control <?= $inv('online_url') ?>" value="<?= $v('online_url') ?>" placeholder="https://…">
                  <div class="ne-form-hint">A public page about the event. Don't paste private meeting links or passwords — share those with registered attendees directly.</div>
                  <?= $err('online_url') ?>
                </div></div>
              </div>
            </div>
          </section>

          <!-- 4. REGISTRATION & PRICING -->
          <section class="ne-form-section" id="sec-register">
            <h2 class="ne-form-section-title"><span class="ne-form-section-num">4</span> Registration &amp; Pricing</h2>
            <p class="ne-form-section-desc">N Events links to your registration page — we don't sell tickets.</p>
            <label class="ne-form-label">Registration Required?</label>
            <div class="ne-choice-row mb-3" style="max-width:320px">
              <label class="ne-choice"><input type="radio" name="registration_required" value="1" <?= $chk('registration_required', '1') ?>><span>Yes</span></label>
              <label class="ne-choice"><input type="radio" name="registration_required" value="0" <?= $chk('registration_required', '0') ?>><span>No</span></label>
            </div>
            <div class="row g-3">
              <div class="col-md-8"><div class="ne-form-group">
                <label class="ne-form-label" for="f-reg">Registration URL</label>
                <input id="f-reg" type="url" name="registration_url" class="ne-form-control <?= $inv('registration_url') ?>" value="<?= $v('registration_url') ?>" placeholder="https://…">
                <?= $err('registration_url') ?>
              </div></div>
              <div class="col-md-4"><div class="ne-form-group">
                <label class="ne-form-label" for="f-dl">Registration Deadline</label>
                <input id="f-dl" type="datetime-local" name="registration_deadline" class="ne-form-control <?= $inv('registration_deadline') ?>" value="<?= $v('registration_deadline') ?>">
                <?= $err('registration_deadline') ?>
              </div></div>
            </div>

            <label class="ne-form-label">Ticket Type</label>
            <div class="ne-choice-row mb-3">
              <?php foreach (['free' => 'Free', 'paid' => 'Paid', 'donation' => 'Donation', 'unknown' => 'Not sure'] as $val => $label): ?>
                <label class="ne-choice"><input type="radio" name="pricing_type" value="<?= $val ?>" <?= $chk('pricing_type', $val) ?> data-preview="price"><span><?= $label ?></span></label>
              <?php endforeach; ?>
            </div>
            <div class="row g-3" data-show-for-pricing="paid">
              <div class="col-4 col-md-3"><div class="ne-form-group">
                <label class="ne-form-label" for="f-cur">Currency</label>
                <select id="f-cur" name="currency" class="ne-form-control" data-preview="price">
                  <?php foreach ($currencies as $c): ?><option value="<?= $c ?>" <?= $sel('currency', $c) ?>><?= $c ?></option><?php endforeach; ?>
                </select>
              </div></div>
              <div class="col-4 col-md-3"><div class="ne-form-group">
                <label class="ne-form-label" for="f-min">Minimum Price *</label>
                <input id="f-min" type="number" name="min_price" min="0" step="0.01" class="ne-form-control <?= $inv('min_price') ?>" value="<?= $v('min_price') ?>" data-preview="price">
              </div></div>
              <div class="col-4 col-md-3"><div class="ne-form-group">
                <label class="ne-form-label" for="f-max">Maximum Price</label>
                <input id="f-max" type="number" name="max_price" min="0" step="0.01" class="ne-form-control <?= $inv('max_price') ?>" value="<?= $v('max_price') ?>" data-preview="price">
              </div></div>
            </div>
            <?= $err('min_price') ?><?= $err('max_price') ?>
          </section>

          <!-- 5. ORGANIZER -->
          <section class="ne-form-section" id="sec-organizer">
            <h2 class="ne-form-section-title"><span class="ne-form-section-num">5</span> Organizer</h2>
            <p class="ne-form-section-desc">Shown on the event page. Email and phone are optional and shown publicly if you add them.</p>
            <?php if (!empty($organizer)): ?>
              <label style="display:flex;gap:.6rem;align-items:center;margin-bottom:1rem;font-size:.9rem;cursor:pointer">
                <input type="checkbox" name="use_organizer_profile" value="1" <?= !empty($old['use_organizer_profile']) ? 'checked' : '' ?> style="accent-color:var(--ne-primary);width:18px;height:18px">
                Post as my organizer profile: <strong><?= View::e($organizer['name']) ?></strong>
              </label>
            <?php endif; ?>
            <div class="row g-3">
              <div class="col-md-6"><div class="ne-form-group">
                <label class="ne-form-label" for="f-org">Organizer Name *</label>
                <input id="f-org" type="text" name="organizer_name" maxlength="200" class="ne-form-control <?= $inv('organizer_name') ?>" value="<?= $v('organizer_name') ?>" data-preview="organizer">
                <?= $err('organizer_name') ?>
              </div></div>
              <div class="col-md-6"><div class="ne-form-group">
                <label class="ne-form-label" for="f-orgmail">Organizer Email</label>
                <input id="f-orgmail" type="email" name="organizer_email" class="ne-form-control <?= $inv('organizer_email') ?>" value="<?= $v('organizer_email') ?>">
                <?= $err('organizer_email') ?>
              </div></div>
              <div class="col-md-6"><div class="ne-form-group">
                <label class="ne-form-label" for="f-orgphone">Organizer Phone</label>
                <input id="f-orgphone" type="tel" name="organizer_phone" class="ne-form-control <?= $inv('organizer_phone') ?>" value="<?= $v('organizer_phone') ?>">
                <?= $err('organizer_phone') ?>
              </div></div>
              <div class="col-md-6"><div class="ne-form-group">
                <label class="ne-form-label" for="f-orgweb">Organizer Website</label>
                <input id="f-orgweb" type="url" name="organizer_website" class="ne-form-control <?= $inv('organizer_website') ?>" value="<?= $v('organizer_website') ?>" placeholder="https://…">
                <?= $err('organizer_website') ?>
              </div></div>
              <div class="col-12"><div class="ne-form-group">
                <label class="ne-form-label" for="f-orgsocial">Organizer Social Profile URL</label>
                <input id="f-orgsocial" type="url" name="organizer_social_url" class="ne-form-control <?= $inv('organizer_social_url') ?>" value="<?= $v('organizer_social_url') ?>" placeholder="https://www.instagram.com/…">
                <?= $err('organizer_social_url') ?>
              </div></div>
            </div>
          </section>

          <!-- 6. IMAGE -->
          <section class="ne-form-section" id="sec-image">
            <h2 class="ne-form-section-title"><span class="ne-form-section-num">6</span> Event Image</h2>
            <p class="ne-form-section-desc">JPEG, PNG or WebP · up to <?= $maxMb ?> MB · at least 300×300 px. Without an image, a category illustration is used.</p>
            <?php if ($currentImage): ?>
              <div style="display:flex;gap:1rem;align-items:center;margin-bottom:1rem">
                <img src="<?= View::e($currentImage) ?>" alt="Current event image" style="width:160px;height:90px;object-fit:cover;border-radius:var(--ne-radius-sm)">
                <label style="font-size:.85rem;display:flex;gap:.5rem;align-items:center;cursor:pointer">
                  <input type="checkbox" name="remove_image" value="1" style="accent-color:var(--ne-accent)"> Remove current image
                </label>
              </div>
            <?php endif; ?>
            <input id="f-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" class="ne-form-control <?= $inv('image') ?>">
            <?= $err('image') ?>
          </section>

          <?php if (!empty($duplicate)): ?>
            <label class="ne-alert ne-alert-warning mb-3" style="cursor:pointer">
              <input type="checkbox" name="confirm_not_duplicate" value="1" style="accent-color:var(--ne-gold);width:18px;height:18px">
              <span>This is a <strong>different</strong> event from "<?= View::e($duplicate['title']) ?>". Submit it for a quick review.</span>
            </label>
          <?php endif; ?>

          <div class="ne-form-submit-row" style="display:flex;gap:.75rem;flex-wrap:wrap">
            <button type="submit" class="btn-ne btn-ne-primary btn-ne-lg">
              <i class="fas <?= $isEdit ? 'fa-save' : 'fa-paper-plane' ?> fa-xs"></i> <?= $isEdit ? 'Save Changes' : 'Publish Event' ?>
            </button>
            <a href="<?= $appUrl ?>/my-events" class="btn-ne btn-ne-ghost btn-ne-lg">Cancel</a>
          </div>
        </div>

        <!-- Sticky side: section nav + live preview -->
        <div class="col-lg-4">
          <div class="ne-form-sticky">
            <nav class="ne-card ne-step-nav mb-3 d-none d-lg-block" style="padding:1rem" aria-label="Form sections">
              <?php $n = 1; foreach ($sections as $id => [$label, $fields]):
                $bad = (bool) array_intersect($fields, array_keys($errors)); ?>
                <a href="#sec-<?= $id ?>" class="<?= $bad ? 'has-error' : '' ?>">
                  <span class="ne-form-section-num" style="width:22px;height:22px;font-size:.7rem"><?= $n++ ?></span>
                  <?= $label ?><?= $bad ? ' <i class="fas fa-exclamation-circle fa-xs ms-auto"></i>' : '' ?>
                </a>
              <?php endforeach; ?>
            </nav>

            <div style="font-size:.72rem;color:var(--ne-text-dim);text-transform:uppercase;letter-spacing:.1em;margin-bottom:.5rem">Live preview</div>
            <article class="event-card" aria-live="polite">
              <div class="event-card-img">
                <img id="pv-img" src="<?= View::e($currentImage ?: $appUrl . '/images/event-fallbacks/default.svg') ?>" alt="">
                <div class="event-card-badges"><span class="badge-ne badge-free" id="pv-price">Free</span><span class="badge-ne badge-offline" id="pv-format">In person</span></div>
              </div>
              <div class="event-card-body">
                <div class="event-card-date" id="pv-date">DATE · TIME</div>
                <h3 class="event-card-title" id="pv-title">Your event title</h3>
                <div class="event-card-by" id="pv-organizer">by Organizer</div>
                <div class="event-card-place"><i class="fas fa-location-dot" aria-hidden="true"></i><span id="pv-location">Location</span></div>
                <div class="event-card-foot"><span class="event-card-category" id="pv-category"></span></div>
              </div>
            </article>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const form = document.getElementById('eventForm');
  const $ = (sel) => form.querySelector(sel);
  const val = (name) => { const el = form.querySelector('[name="' + name + '"]:checked') || form.querySelector('[name="' + name + '"]'); return el ? el.value.trim() : ''; };

  function toggleSections() {
    const fmt = val('format') || 'offline';
    form.querySelectorAll('[data-show-for]').forEach(el => { el.hidden = !el.dataset.showFor.split(' ').includes(fmt); });
    form.querySelectorAll('[data-show-for-pricing]').forEach(el => { el.hidden = val('pricing_type') !== el.dataset.showForPricing; });
  }

  function preview() {
    document.getElementById('pv-title').textContent = val('title') || 'Your event title';
    const cat = $('[name="primary_category_id"]');
    document.getElementById('pv-category').textContent = cat.value ? cat.options[cat.selectedIndex].text.replace(/^— /, '') : '';
    const fmt = val('format') || 'offline';
    document.getElementById('pv-format').textContent = { offline: 'In person', online: 'Online', hybrid: 'Hybrid' }[fmt] || fmt;
    const d = val('start_date'), t = val('start_time');
    if (d) {
      const dt = new Date(d + 'T' + (t || '00:00'));
      document.getElementById('pv-date').textContent = isNaN(dt) ? d
        : dt.toLocaleDateString('en-IN', { weekday: 'short', day: 'numeric', month: 'short' }).toUpperCase() + (t ? ' · ' + dt.toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit' }) : '');
    }
    const dist = $('#f-district');
    const loc = fmt === 'online' ? 'Online' : [val('venue_name'), val('city_area'), dist && $('[name="district_id"]').value ? dist.value : ''].filter(Boolean).join(', ');
    document.getElementById('pv-location').textContent = loc || 'Location';
    document.getElementById('pv-organizer').textContent = 'by ' + (val('organizer_name') || 'Organizer');
    const p = val('pricing_type'), price = document.getElementById('pv-price');
    if (p === 'free') { price.textContent = 'Free'; price.className = 'badge-ne badge-free'; }
    else if (p === 'paid') {
      const sym = val('currency') === 'INR' ? '₹' : val('currency') + ' ';
      const min = val('min_price');
      price.textContent = min ? sym + Number(min).toLocaleString('en-IN') : 'Paid';
      price.className = 'badge-ne badge-price';
    } else { price.textContent = p === 'donation' ? 'Donation' : 'Price'; price.className = 'badge-ne badge-price'; }
  }

  document.getElementById('f-image').addEventListener('change', function () {
    const f = this.files && this.files[0];
    if (!f || !/^image\/(jpeg|png|webp)$/.test(f.type)) return;
    const r = new FileReader();
    r.onload = e => { document.getElementById('pv-img').src = e.target.result; };
    r.readAsDataURL(f);
  });

  // Keep end date sensible when start changes (server re-validates regardless)
  $('[name="start_date"]').addEventListener('change', function () {
    const end = $('[name="end_date"]');
    if (!end.value || end.value < this.value) end.value = this.value;
  });

  form.addEventListener('input', preview);
  form.addEventListener('change', () => { toggleSections(); preview(); });
  toggleSections();
  preview();

  // ── Step-by-step wizard on phones/tablets (the one-page form stays on desktop / without JS) ──
  const steps  = [...form.querySelectorAll('.ne-form-section')];
  const head   = form.querySelector('.ne-wizard-head');
  const side   = form.querySelector('.ne-form-sticky')?.closest('.col-lg-4');
  const submitRow = form.querySelector('.ne-form-submit-row');
  const dupConfirm = form.querySelector('[name="confirm_not_duplicate"]')?.closest('label');
  const mq = window.matchMedia('(max-width: 991.98px)');
  let cur = 0, bar = null;

  const titleOf = el => (el.querySelector('.ne-form-section-title')?.textContent || '').replace(/^\s*\d+\s*/, '').trim();
  const visibleFields = el => [...el.querySelectorAll('input, select, textarea')].filter(f => f.type !== 'hidden' && f.offsetParent !== null);

  function buildBar() {
    bar = document.createElement('div');
    bar.className = 'ne-wizard-bar';
    bar.innerHTML = '<button type="button" class="btn-ne btn-ne-ghost" data-wz-back><i class="fas fa-arrow-left fa-xs"></i> Back</button>'
                  + '<button type="button" class="btn-ne btn-ne-primary" data-wz-next>Next <i class="fas fa-arrow-right fa-xs"></i></button>';
    form.appendChild(bar);
    bar.querySelector('[data-wz-back]').addEventListener('click', () => show(cur - 1, true));
    bar.querySelector('[data-wz-next]').addEventListener('click', () => {
      const bad = visibleFields(steps[cur]).find(f => !f.checkValidity());
      if (bad) { bad.reportValidity(); bad.focus(); return; }
      if (cur < steps.length - 1) show(cur + 1, true); else form.requestSubmit();
    });
  }

  function show(i, scroll) {
    cur = Math.max(0, Math.min(steps.length - 1, i));
    steps.forEach((s, n) => s.classList.toggle('is-current', n === cur));
    const last = cur === steps.length - 1;
    head.querySelector('[data-wz-step]').textContent = cur + 1;
    head.querySelector('[data-wz-title]').textContent = titleOf(steps[cur]);
    head.querySelector('[role=progressbar]').setAttribute('aria-valuenow', cur + 1);
    head.querySelector('[data-wz-bar]').style.width = ((cur + 1) / steps.length * 100) + '%';
    bar.querySelector('[data-wz-back]').disabled = cur === 0;
    bar.querySelector('[data-wz-next]').innerHTML = last
      ? '<i class="fas <?= $isEdit ? 'fa-save' : 'fa-paper-plane' ?> fa-xs"></i> <?= $isEdit ? 'Save changes' : 'Publish' ?>'
      : 'Next <i class="fas fa-arrow-right fa-xs"></i>';
    if (side) side.classList.toggle('is-review', last);        // card preview as the final review
    if (dupConfirm) dupConfirm.hidden = !last;
    if (scroll) head.scrollIntoView({ block: 'start', behavior: 'smooth' });
  }

  function setMode(on) {
    form.classList.toggle('is-wizard', on);
    document.body.classList.toggle('wizard-on', on);
    head.hidden = !on;
    if (on) {
      if (!bar) buildBar();
      bar.hidden = false;
      // after a failed submit, open the first step with an error
      const errStep = steps.findIndex(s => s.querySelector('.is-invalid, .ne-form-error'));
      show(errStep >= 0 ? errStep : cur, false);
    } else {
      if (bar) bar.hidden = true;
      steps.forEach(s => s.classList.remove('is-current'));
      if (side) side.classList.remove('is-review');
      if (dupConfirm) dupConfirm.hidden = false;
    }
  }
  if (steps.length > 1 && head) {
    // inside the app it is always step by step, tablets included
    const app = document.body.classList.contains('is-app');
    setMode(app || mq.matches);
    mq.addEventListener('change', e => setMode(app || e.matches));
  }
})();
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
