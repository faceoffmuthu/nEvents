<?php

declare(strict_types=1);

namespace NEvents\Services\Events;

use NEvents\Core\Application;
use NEvents\Core\Database\Connection;
use NEvents\Services\Audit\AuditLogger;
use NEvents\Services\Location\DistrictService;
use NEvents\Services\Notifications\NotificationService;
use NEvents\Services\Security\UrlValidator;
use Ramsey\Uuid\Uuid;

/**
 * Logged-in user event posting: validation, risk checks, duplicate
 * detection, persistence into the SAME canonical events table every other
 * origin uses, ownership-checked editing, cancellation and deletion.
 *
 * Publication rule: a submission that passes every automatic check is
 * published immediately (status=published). Only concrete risk signals —
 * suspicious content, a possible duplicate, a submitter with prior
 * moderation problems — hold it as status=pending / moderation_status=needs_review.
 *
 * Descriptions are PLAIN TEXT. Any HTML tag is rejected at validation, and
 * every template escapes on output (nl2br(View::e(...))), so there is no
 * rich-text surface for stored XSS.
 */
class UserEventService
{
    public const LANGUAGES  = ['en' => 'English', 'ta' => 'Tamil', 'hi' => 'Hindi', 'te' => 'Telugu', 'ml' => 'Malayalam', 'kn' => 'Kannada', 'other' => 'Other'];
    public const CURRENCIES = ['INR', 'USD', 'EUR', 'GBP', 'SGD', 'AED'];
    public const FORMATS    = ['offline', 'online', 'hybrid'];
    public const PRICING    = ['free', 'paid', 'donation', 'unknown'];

    /** Content that holds a submission for review (not an automatic rejection). */
    private const SUSPICIOUS_PATTERNS = [
        '/\b(casino|betting|satta|lottery|jackpot)\b/i',
        '/\b(loan approv|instant loan|guaranteed returns?|double your (money|crypto))\b/i',
        '/\b(escort|xxx|porn|adult dating)\b/i',
        '/\b(work from home|earn \d+k? (per|a) (day|week))\b/i',
        '/\b(whatsapp|telegram) (me|us) (for|to) (earn|invest)/i',
    ];

    private const HTML_TAG = '/<\s*\/?\s*[a-z!?][^>]*>/i';

    public function __construct(
        private Connection               $db,
        private DistrictService          $districts,
        private DuplicateDetectionService $duplicates,
        private EventImageUploadService  $images,
        private NotificationService      $notifications,
        private AuditLogger              $audit,
    ) {}

    // ==================================================================
    // Queries
    // ==================================================================

    public function organizerForUser(int $userId): array|false
    {
        return $this->db->selectOne(
            "SELECT o.* FROM organizers o JOIN organizer_users ou ON ou.organizer_id = o.id
              WHERE ou.user_id = :uid AND ou.role IN ('owner','editor') AND o.status = 'active' LIMIT 1",
            [':uid' => $userId]
        );
    }

    /** Event the user may manage: their own, or any when $isModerator. Server-side check. */
    public function findManageable(int $eventId, int $userId, bool $isModerator): array|false
    {
        $event = $this->db->selectOne("SELECT * FROM events WHERE id = :id", [':id' => $eventId]);
        if (!$event) {
            return false;
        }
        if (!$isModerator && (int) ($event['created_by_user_id'] ?? 0) !== $userId) {
            return false;
        }
        return $event;
    }

    /** All events the user has posted, grouped for the My Events tabs. */
    public function myEvents(int $userId): array
    {
        $rows = $this->db->select(
            "SELECT e.id, e.slug, e.title, e.status, e.moderation_status, e.moderation_reason, e.format,
                    e.pricing_type, e.min_price, e.max_price, e.currency, e.featured_image_url,
                    e.view_count, e.click_count, e.save_count, e.created_at, e.published_at,
                    (SELECT MIN(eo.start_at_utc) FROM event_occurrences eo WHERE eo.event_id = e.id) AS next_start,
                    (SELECT MAX(COALESCE(eo.end_at_utc, eo.start_at_utc)) FROM event_occurrences eo WHERE eo.event_id = e.id) AS last_end,
                    d.name AS district_name, c.name AS city_name, v.name AS venue_name,
                    catp.slug AS category_slug, catp.name AS category_name, catp.color AS category_color, catp.icon AS category_icon,
                    (SELECT COUNT(*) FROM reports r WHERE r.event_id = e.id AND r.status IN ('open','reviewing')) AS open_reports
               FROM events e
               LEFT JOIN cities c ON c.id = e.city_id
               LEFT JOIN districts d ON d.id = c.district_id
               LEFT JOIN venues v ON v.id = e.venue_id
               LEFT JOIN event_categories ecp ON ecp.event_id = e.id AND ecp.is_primary = 1
               LEFT JOIN categories catp ON catp.id = ecp.category_id
              WHERE e.created_by_user_id = :uid
              ORDER BY e.created_at DESC",
            [':uid' => $userId]
        );

        $now = gmdate('Y-m-d H:i:s');
        $groups = ['upcoming' => [], 'review' => [], 'completed' => [], 'cancelled' => [], 'rejected' => [], 'draft' => []];
        foreach ($rows as $r) {
            $ended = $r['last_end'] !== null && $r['last_end'] < $now;
            $key = match (true) {
                $r['status'] === 'cancelled'                                            => 'cancelled',
                in_array($r['status'], ['rejected', 'archived'], true)
                    || in_array($r['moderation_status'], ['rejected', 'suspended'], true) => 'rejected',
                $r['status'] === 'draft'                                                 => 'draft',
                $r['status'] === 'completed' || $r['status'] === 'expired' || $ended     => 'completed',
                $r['status'] === 'pending'                                               => 'review',
                default                                                                  => 'upcoming',
            };
            $r['group'] = $key;
            $groups[$key][] = $r;
        }
        return $groups;
    }

    /** Current values for the edit form. */
    public function formValues(array $event): array
    {
        $tz  = new \DateTimeZone($event['timezone'] ?: 'Asia/Kolkata');
        $occ = $this->db->selectOne("SELECT * FROM event_occurrences WHERE event_id = :id ORDER BY start_at_utc ASC LIMIT 1", [':id' => $event['id']]);
        $start = $occ ? (new \DateTimeImmutable($occ['start_at_utc'], new \DateTimeZone('UTC')))->setTimezone($tz) : null;
        $end   = ($occ && $occ['end_at_utc']) ? (new \DateTimeImmutable($occ['end_at_utc'], new \DateTimeZone('UTC')))->setTimezone($tz) : null;
        $venue = $event['venue_id'] ? $this->db->selectOne("SELECT * FROM venues WHERE id = :id", [':id' => $event['venue_id']]) : false;
        $cats  = $this->db->select("SELECT category_id, is_primary FROM event_categories WHERE event_id = :id", [':id' => $event['id']]);
        $tags  = $this->db->select("SELECT t.name FROM tags t JOIN event_tags et ON et.tag_id = t.id WHERE et.event_id = :id", [':id' => $event['id']]);
        $deadline = $event['registration_deadline']
            ? (new \DateTimeImmutable($event['registration_deadline'], new \DateTimeZone('UTC')))->setTimezone($tz)->format('Y-m-d\TH:i')
            : '';

        $primary = '';
        $additional = [];
        foreach ($cats as $c) {
            if ($c['is_primary']) $primary = (string) $c['category_id'];
            else $additional[] = (string) $c['category_id'];
        }

        return [
            'title' => $event['title'], 'short_summary' => $event['short_summary'], 'description' => $event['description'],
            'primary_category_id' => $primary, 'additional_category_ids' => $additional,
            'tags' => implode(', ', array_column($tags, 'name')), 'primary_language' => $event['primary_language'],
            'format' => $event['format'], 'timezone' => $event['timezone'],
            'start_date' => $start?->format('Y-m-d') ?? '', 'start_time' => $start?->format('H:i') ?? '',
            'end_date' => $end?->format('Y-m-d') ?? '', 'end_time' => $end?->format('H:i') ?? '',
            'district_id' => $venue['district_id'] ?? '', 'city_area' => $venue['locality'] ?? '',
            'venue_name' => $venue['name'] ?? '', 'address' => $venue['address'] ?? '', 'postal_code' => $venue['postal_code'] ?? '',
            'latitude' => $venue['latitude'] ?? '', 'longitude' => $venue['longitude'] ?? '', 'map_url' => $venue['map_url'] ?? '',
            'online_platform' => $event['online_platform'], 'online_url' => $event['online_url'],
            'registration_required' => (string) $event['registration_required'], 'registration_url' => $event['registration_url'],
            'registration_deadline' => $deadline,
            'pricing_type' => $event['pricing_type'], 'currency' => $event['currency'],
            'min_price' => $event['min_price'], 'max_price' => $event['max_price'],
            'use_organizer_profile' => $event['primary_organizer_id'] ? '1' : '',
            'organizer_name' => $event['organizer_display_name'], 'organizer_email' => $event['organizer_email'],
            'organizer_phone' => $event['organizer_phone'], 'organizer_website' => $event['organizer_website'],
            'organizer_social_url' => $event['organizer_social_url'],
            'featured_image_url' => $event['featured_image_url'],
        ];
    }

    // ==================================================================
    // Create / update
    // ==================================================================

    /**
     * @return array{ok:bool, errors?:array, event_id?:int, slug?:string, status?:string, review_reason?:string, duplicate?:array}
     */
    public function create(int $userId, bool $isModerator, array $input, ?array $imageFile): array
    {
        $user = $this->db->selectOne("SELECT id, status, can_post_events FROM users WHERE id = :id", [':id' => $userId]);
        if (!$user || $user['status'] !== 'active' || !(int) $user['can_post_events']) {
            return ['ok' => false, 'errors' => ['_general' => 'Your account is not currently allowed to post events. Contact support if you think this is a mistake.']];
        }

        if (!$isModerator && ($limitError = $this->rateLimitError($userId))) {
            return ['ok' => false, 'errors' => ['_general' => $limitError]];
        }

        [$errors, $data] = $this->validate($input, $userId, null);

        // Exact re-submission by the same user within 24h is a mistake, not a new event
        if (!$errors) {
            $same = $this->db->selectOne(
                "SELECT id FROM events WHERE created_by_user_id = :uid AND normalized_title = :t
                    AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) AND status <> 'cancelled' LIMIT 1",
                [':uid' => $userId, ':t' => $data['normalized_title']]
            );
            if ($same) {
                $errors['title'] = 'You already posted an event with this title in the last 24 hours. Edit it from My Events instead.';
            }
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $dup = $this->checkDuplicate($data, null);
        if ($dup['block'] && empty($input['confirm_not_duplicate'])) {
            return ['ok' => false, 'errors' => ['_duplicate' => 'This looks like an event that is already listed.'], 'duplicate' => $dup['match']];
        }

        $image = $this->images->store($imageFile);
        if (!$image['ok']) {
            return ['ok' => false, 'errors' => ['image' => $image['error']]];
        }

        [$status, $moderation, $reason] = $this->decidePublication($userId, $data, $dup);
        $data['featured_image_url'] = $image['path'] ?? null;

        // Origin is internal only (never shown as a badge): posting as an organizer
        // profile = organizer_submitted, staff = admin_created, everyone else = user_submitted.
        $origin = $data['primary_organizer_id'] ? 'organizer_submitted' : ($isModerator ? 'admin_created' : 'user_submitted');

        $eventId = $this->db->transaction(function () use ($userId, $data, $status, $moderation, $reason, $image, $origin) {
            $venueId = $this->saveVenue(null, $data);
            $eventId = $this->db->insert(
                "INSERT INTO events
                    (uuid, slug, title, normalized_title, short_summary, description, primary_organizer_id,
                     organizer_display_name, organizer_email, organizer_phone, organizer_website, organizer_social_url,
                     event_type, format, status, data_origin, created_by_user_id, moderation_status, moderation_reason,
                     verification_status, trust_score, primary_language, registration_url, registration_required,
                     registration_deadline, timezone, pricing_type, currency, min_price, max_price, venue_id, city_id,
                     online_platform, online_url, featured_image_url, first_published_at, published_at, last_seen_at)
                 VALUES
                    (:uuid, :slug, :title, :ntitle, :summary, :description, :org_id,
                     :org_name, :org_email, :org_phone, :org_web, :org_social,
                     'community', :format, :status, :origin, :uid, :moderation, :reason,
                     'unverified', 50, :lang, :reg_url, :reg_required,
                     :reg_deadline, :tz, :pricing, :currency, :min_price, :max_price, :venue_id, :city_id,
                     :online_platform, :online_url, :image, :first_pub, :pub, NOW())",
                [
                    ':uuid' => Uuid::uuid4()->toString(), ':slug' => $this->uniqueSlug($data['title']),
                    ':uid' => $userId, ':status' => $status, ':moderation' => $moderation, ':reason' => $reason, ':origin' => $origin,
                    ':first_pub' => $status === 'published' ? date('Y-m-d H:i:s') : null,
                    ':pub'       => $status === 'published' ? date('Y-m-d H:i:s') : null,
                    ':venue_id'  => $venueId, ':image' => $data['featured_image_url'],
                ] + $this->eventParams($data)
            );
            $this->saveOccurrence($eventId, $data, true);
            $this->saveCategoriesAndTags($eventId, $data);
            if (!empty($image['path'])) {
                $this->db->insert(
                    "INSERT INTO event_images (event_id, local_path, alt_text, is_primary, usage_status, width, height)
                     VALUES (:e, :p, :alt, 1, 'approved', :w, :h)",
                    [':e' => $eventId, ':p' => $image['path'], ':alt' => mb_substr($data['title'], 0, 300), ':w' => $image['width'], ':h' => $image['height']]
                );
            }
            return $eventId;
        });

        if ($dup['match'] && $dup['score'] >= $this->threshold('duplicate_review_score')) {
            $this->duplicates->recordCandidate($dup['match']['event_id'], $eventId, $dup['score'], $dup['signals'] + ['origin' => 'user_submission']);
        }

        $this->audit->log($userId, 'event.create', 'event', $eventId, "User posted event ({$status})", ['moderation' => $moderation, 'reason' => $reason]);
        $this->notifications->notifyOwner($eventId, $status === 'published' ? 'published' : 'needs_review', $status === 'published' ? '' : $this->reasonForHumans($reason));

        $slug = $this->db->selectOne("SELECT slug FROM events WHERE id = :id", [':id' => $eventId])['slug'];
        return ['ok' => true, 'event_id' => $eventId, 'slug' => $slug, 'status' => $status, 'review_reason' => $reason];
    }

    public function update(int $eventId, int $userId, bool $isModerator, array $input, ?array $imageFile): array
    {
        $event = $this->findManageable($eventId, $userId, $isModerator);
        if (!$event) {
            return ['ok' => false, 'forbidden' => true, 'errors' => ['_general' => 'You can only edit events you posted.']];
        }
        if (in_array($event['status'], ['cancelled', 'completed', 'expired', 'archived'], true)) {
            return ['ok' => false, 'errors' => ['_general' => 'This event is ' . $event['status'] . ' and can no longer be edited.']];
        }

        $before = $this->formValues($event);
        [$errors, $data] = $this->validate($input, (int) $event['created_by_user_id'], $event);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $keyChanged = $data['start_utc'] !== $this->firstStartUtc($eventId)
            || $data['normalized_title'] !== $event['normalized_title']
            || (string) ($data['district_id'] ?? '') !== (string) $before['district_id'];

        $dup = $keyChanged ? $this->checkDuplicate($data, $eventId) : ['block' => false, 'match' => null, 'score' => 0, 'signals' => []];
        if ($dup['block'] && empty($input['confirm_not_duplicate'])) {
            return ['ok' => false, 'errors' => ['_duplicate' => 'After this change the event looks like one that is already listed.'], 'duplicate' => $dup['match']];
        }

        $image = $this->images->store($imageFile);
        if (!$image['ok']) {
            return ['ok' => false, 'errors' => ['image' => $image['error']]];
        }
        $oldImage = $event['featured_image_url'];
        $data['featured_image_url'] = $image['path'] ?? (!empty($input['remove_image']) ? null : $oldImage);

        // Moderator decisions stick: an owner edit never auto-republishes an
        // event a moderator rejected, suspended or unpublished, and never clears a flag.
        $moderatorHold = in_array($event['moderation_status'], ['rejected', 'suspended'], true)
            || ($event['moderation_status'] === 'needs_review' && str_starts_with((string) $event['moderation_reason'], 'moderator'));
        if ($moderatorHold && !$isModerator) {
            [$status, $moderation, $reason] = [$event['status'], $event['moderation_status'], $event['moderation_reason']];
        } else {
            [$status, $moderation, $reason] = $this->decidePublication((int) $event['created_by_user_id'], $data, $dup);
            if ($event['moderation_status'] === 'flagged' && $moderation === 'clean') {
                [$moderation, $reason] = ['flagged', $event['moderation_reason']];
            }
        }

        $this->db->transaction(function () use ($event, $eventId, $data, $status, $moderation, $reason, $image) {
            $venueId = $this->saveVenue($event['venue_id'] ? (int) $event['venue_id'] : null, $data);
            $this->db->update(
                "UPDATE events SET title = :title, normalized_title = :ntitle, short_summary = :summary, description = :description,
                        primary_organizer_id = :org_id, organizer_display_name = :org_name, organizer_email = :org_email,
                        organizer_phone = :org_phone, organizer_website = :org_web, organizer_social_url = :org_social,
                        format = :format, status = :status, moderation_status = :moderation, moderation_reason = :reason,
                        primary_language = :lang, registration_url = :reg_url, registration_required = :reg_required,
                        registration_deadline = :reg_deadline, timezone = :tz, pricing_type = :pricing, currency = :currency,
                        min_price = :min_price, max_price = :max_price, venue_id = :venue_id, city_id = :city_id,
                        online_platform = :online_platform, online_url = :online_url, featured_image_url = :image,
                        published_at = IF(:status2 = 'published' AND status <> 'published', NOW(), published_at),
                        first_published_at = COALESCE(first_published_at, IF(:status3 = 'published', NOW(), NULL)),
                        updated_at = NOW()
                  WHERE id = :id",
                [
                    ':id' => $eventId, ':status' => $status, ':status2' => $status, ':status3' => $status,
                    ':moderation' => $moderation, ':reason' => $reason,
                    ':venue_id' => $venueId, ':image' => $data['featured_image_url'],
                ] + $this->eventParams($data)
            );
            $this->saveOccurrence($eventId, $data, false);
            $this->db->delete("DELETE FROM event_categories WHERE event_id = :id", [':id' => $eventId]);
            $this->db->delete("DELETE FROM event_tags WHERE event_id = :id", [':id' => $eventId]);
            $this->saveCategoriesAndTags($eventId, $data);
            if (!empty($image['path'])) {
                $this->db->update("UPDATE event_images SET is_primary = 0 WHERE event_id = :id", [':id' => $eventId]);
                $this->db->insert(
                    "INSERT INTO event_images (event_id, local_path, alt_text, is_primary, usage_status, width, height)
                     VALUES (:e, :p, :alt, 1, 'approved', :w, :h)",
                    [':e' => $eventId, ':p' => $image['path'], ':alt' => mb_substr($data['title'], 0, 300), ':w' => $image['width'], ':h' => $image['height']]
                );
            }
        });

        if ($oldImage && $oldImage !== $data['featured_image_url']) {
            $this->images->delete($oldImage);
            $this->db->delete("DELETE FROM event_images WHERE event_id = :id AND local_path = :p", [':id' => $eventId, ':p' => $oldImage]);
        }

        if ($dup['match'] && $dup['score'] >= $this->threshold('duplicate_review_score')) {
            $this->duplicates->recordCandidate($dup['match']['event_id'], $eventId, $dup['score'], $dup['signals'] + ['origin' => 'user_edit']);
        }

        // Tell people who saved it — only for changes that affect attending
        $after   = $this->formValues($this->db->selectOne("SELECT * FROM events WHERE id = :id", [':id' => $eventId]));
        $changes = $this->meaningfulChanges($before, $after);
        if ($changes && $event['status'] === 'published') {
            $this->notifications->cancelQueuedForEvent($eventId); // reminders are re-queued with the new time
            $this->notifications->notifySaversOfChange($eventId, 'updated', implode('; ', $changes) . '.');
        }
        if ($event['status'] === 'published' && $status !== 'published') {
            $this->notifications->notifyOwner($eventId, 'needs_review', $this->reasonForHumans($reason));
        }

        $this->audit->log($userId, 'event.update', 'event', $eventId, 'Event edited' . ($isModerator && (int) $event['created_by_user_id'] !== $userId ? ' by moderator' : ''),
            ['changes' => $changes, 'status' => $status, 'moderation' => $moderation]);

        return ['ok' => true, 'event_id' => $eventId, 'slug' => $event['slug'], 'status' => $status, 'review_reason' => $reason, 'changes' => $changes];
    }

    // ==================================================================
    // Lifecycle
    // ==================================================================

    public function cancel(int $eventId, int $userId, bool $isModerator): array
    {
        $event = $this->findManageable($eventId, $userId, $isModerator);
        if (!$event) {
            return ['ok' => false, 'forbidden' => true, 'error' => 'You can only cancel events you posted.'];
        }
        if (!in_array($event['status'], ['published', 'pending', 'postponed', 'draft'], true)) {
            return ['ok' => false, 'error' => 'This event cannot be cancelled (status: ' . $event['status'] . ').'];
        }

        $this->db->transaction(function () use ($eventId) {
            $this->db->update("UPDATE events SET status = 'cancelled', updated_at = NOW() WHERE id = :id", [':id' => $eventId]);
            $this->db->update("UPDATE event_occurrences SET status = 'cancelled' WHERE event_id = :id AND status IN ('scheduled','postponed')", [':id' => $eventId]);
        });

        $this->notifications->cancelQueuedForEvent($eventId);
        if ($event['status'] === 'published') {
            $this->notifications->notifySaversOfChange($eventId, 'cancelled', 'Please don\'t travel to the venue.');
        }
        $this->audit->log($userId, 'event.cancel', 'event', $eventId, 'Event cancelled by ' . ($isModerator && (int) $event['created_by_user_id'] !== $userId ? 'moderator' : 'owner'));

        return ['ok' => true];
    }

    /**
     * Hard delete is only for events that were never public (drafts,
     * submissions still under review, rejected ones). Anything that was ever
     * published keeps its record and must be cancelled instead.
     */
    public function delete(int $eventId, int $userId, bool $isModerator): array
    {
        $event = $this->findManageable($eventId, $userId, $isModerator);
        if (!$event) {
            return ['ok' => false, 'forbidden' => true, 'error' => 'You can only delete events you posted.'];
        }
        if ($event['first_published_at'] !== null || $event['status'] === 'published') {
            return ['ok' => false, 'error' => 'Events that have been published can be cancelled, but not deleted.'];
        }

        $this->db->delete("DELETE FROM events WHERE id = :id", [':id' => $eventId]);
        if ($event['venue_id']) {
            $this->db->delete("DELETE FROM venues WHERE id = :id AND NOT EXISTS (SELECT 1 FROM events WHERE venue_id = :id2)", [':id' => $event['venue_id'], ':id2' => $event['venue_id']]);
        }
        $this->images->delete($event['featured_image_url']);
        $this->audit->log($userId, 'event.delete', 'event', $eventId, 'Unpublished event deleted: ' . $event['title']);

        return ['ok' => true];
    }

    // ==================================================================
    // Validation
    // ==================================================================

    /** @return array{0: array<string,string>, 1: array} [errors, clean data] */
    public function validate(array $in, int $ownerId, ?array $existing): array
    {
        $e = [];
        $d = [];
        $s = fn(string $k): string => trim((string) ($in[$k] ?? ''));

        // --- Basic information
        $d['title'] = preg_replace('/\s+/', ' ', $s('title'));
        $len = mb_strlen($d['title']);
        if ($len < 5 || $len > 150) $e['title'] = 'Title must be 5–150 characters.';
        $d['short_summary'] = preg_replace('/\s+/', ' ', $s('short_summary'));
        $len = mb_strlen($d['short_summary']);
        if ($len < 20 || $len > 300) $e['short_summary'] = 'Short summary must be 20–300 characters.';
        $d['description'] = str_replace("\r\n", "\n", $s('description'));
        $len = mb_strlen($d['description']);
        if ($len < 50 || $len > 5000) $e['description'] = 'Description must be 50–5000 characters.';

        foreach (['title', 'short_summary', 'description'] as $f) {
            if (!isset($e[$f]) && preg_match(self::HTML_TAG, $d[$f])) {
                $e[$f] = 'HTML and scripts are not allowed — please use plain text.';
            }
        }
        $d['normalized_title'] = mb_substr(preg_replace('/\s+/', ' ', mb_strtolower($d['title'])), 0, 300);

        $d['primary_category_id'] = (int) ($in['primary_category_id'] ?? 0);
        $validCats = array_map('intval', array_column($this->db->select("SELECT id FROM categories WHERE status = 'active'"), 'id'));
        if (!in_array($d['primary_category_id'], $validCats, true)) $e['primary_category_id'] = 'Choose a primary category.';
        $extra = array_unique(array_map('intval', (array) ($in['additional_category_ids'] ?? [])));
        $extra = array_values(array_filter($extra, fn($id) => $id !== $d['primary_category_id']));
        if (count($extra) > 3) $e['additional_category_ids'] = 'Choose at most 3 additional categories.';
        if (array_diff($extra, $validCats)) $e['additional_category_ids'] = 'Invalid category selected.';
        $d['additional_category_ids'] = $extra;

        $d['tags'] = [];
        foreach (array_filter(array_map('trim', explode(',', $s('tags')))) as $tag) {
            if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N} .+#-]{1,29}$/u', $tag)) {
                $e['tags'] = 'Tags must be 2–30 letters/numbers, separated by commas.';
                break;
            }
            $d['tags'][mb_strtolower($tag)] = $tag;
        }
        if (count($d['tags']) > 8) $e['tags'] = 'Use at most 8 tags.';

        $d['primary_language'] = array_key_exists($s('primary_language'), self::LANGUAGES) ? $s('primary_language') : 'en';
        $d['format'] = $s('format');
        if (!in_array($d['format'], self::FORMATS, true)) $e['format'] = 'Choose Offline, Online or Hybrid.';

        // --- Date & time
        $d['timezone'] = $s('timezone') ?: 'Asia/Kolkata';
        if (!in_array($d['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            $e['timezone'] = 'Choose a valid timezone.';
            $d['timezone'] = 'Asia/Kolkata';
        }
        $tz    = new \DateTimeZone($d['timezone']);
        $start = $this->parseLocal($s('start_date'), $s('start_time'), $tz);
        $end   = $this->parseLocal($s('end_date'), $s('end_time'), $tz);
        $now   = new \DateTimeImmutable('now', $tz);
        if (!$start) $e['start_date'] = 'Enter a valid start date and time.';
        if (!$end)   $e['end_date']   = 'Enter a valid end date and time.';
        if ($start && $end) {
            if ($end < $start) {
                $e['end_date'] = 'The event must end after it starts.';
            } elseif ($end <= $now) {
                $e['end_date'] = 'This event has already ended — only upcoming or ongoing events can be posted.';
            } elseif ($start > $now->modify('+2 years')) {
                $e['start_date'] = 'Events can be posted up to 2 years ahead.';
            } elseif ($end > $start->modify('+60 days')) {
                $e['end_date'] = 'Events can last at most 60 days. Post recurring sessions separately.';
            }
        }
        $utc = new \DateTimeZone('UTC');
        $d['start_utc'] = $start?->setTimezone($utc)->format('Y-m-d H:i:s');
        $d['end_utc']   = $end?->setTimezone($utc)->format('Y-m-d H:i:s');

        // --- Location
        $d['district_id'] = null;
        $d['city_id']     = null;
        $needsVenue = in_array($d['format'], ['offline', 'hybrid'], true);
        foreach (['city_area', 'venue_name', 'address', 'postal_code', 'map_url', 'latitude', 'longitude'] as $f) {
            $d[$f] = $needsVenue ? $s($f) : '';
        }
        if ($needsVenue) {
            $districtId = (int) ($in['district_id'] ?? 0);
            if (!$districtId || !$this->districts->isValidActiveDistrict($districtId)) {
                $e['district_id'] = 'Choose the state and district where the event takes place.';
            } else {
                $d['district_id'] = $districtId;
                $d['city_id'] = $this->resolveCityId($districtId, $d['city_area']);
            }
            if (mb_strlen($d['city_area']) < 2 || mb_strlen($d['city_area']) > 100) $e['city_area'] = 'Enter the city or area (2–100 characters).';
            if (mb_strlen($d['venue_name']) < 2 || mb_strlen($d['venue_name']) > 200) $e['venue_name'] = 'Enter the venue name.';
            if (mb_strlen($d['address']) < 5 || mb_strlen($d['address']) > 500) $e['address'] = 'Enter the venue address.';
            foreach (['city_area', 'venue_name', 'address'] as $f) {
                if (!isset($e[$f]) && preg_match(self::HTML_TAG, $d[$f])) $e[$f] = 'HTML is not allowed.';
            }
            if ($d['postal_code'] !== '' && !preg_match('/^[1-9]\d{5}$/', $d['postal_code'])) $e['postal_code'] = 'Indian PIN codes are 6 digits and do not start with 0.';
            if ($d['map_url'] !== '' && !UrlValidator::isAcceptable($d['map_url'])) $e['map_url'] = 'Enter a valid http(s) map link.';
            if ($d['latitude'] !== '' || $d['longitude'] !== '') {
                $lat = filter_var($d['latitude'], FILTER_VALIDATE_FLOAT);
                $lng = filter_var($d['longitude'], FILTER_VALIDATE_FLOAT);
                if ($lat === false || $lng === false || $lat < 6.5 || $lat > 37.5 || $lng < 68.0 || $lng > 97.5) {
                    $e['latitude'] = 'Coordinates must be a valid latitude/longitude inside India (or leave both empty).';
                }
            }
        }

        $d['online_platform'] = in_array($d['format'], ['online', 'hybrid'], true) ? mb_substr($s('online_platform'), 0, 100) : '';
        $d['online_url']      = in_array($d['format'], ['online', 'hybrid'], true) ? $s('online_url') : '';
        if ($d['online_url'] !== '' && !UrlValidator::isAcceptable($d['online_url'])) $e['online_url'] = 'Enter a valid public http(s) URL.';
        if (preg_match(self::HTML_TAG, $d['online_platform'])) $e['online_platform'] = 'HTML is not allowed.';

        // --- Registration
        $d['registration_required'] = ($in['registration_required'] ?? '0') === '1' ? 1 : 0;
        $d['registration_url'] = $s('registration_url');
        if ($d['registration_url'] !== '' && !UrlValidator::isAcceptable($d['registration_url'])) {
            $e['registration_url'] = 'Enter a valid public http(s) registration link.';
        } elseif ($d['registration_required'] && $d['registration_url'] === '') {
            $e['registration_url'] = 'Registration is required, so add the registration link.';
        }
        if ($d['format'] === 'online' && $d['online_url'] === '' && $d['registration_url'] === '') {
            $e['online_url'] = 'Online events need a public information URL or a registration link.';
        }
        $d['registration_deadline_utc'] = null;
        if ($s('registration_deadline') !== '') {
            try {
                $dl = new \DateTimeImmutable($s('registration_deadline'), $tz);
                if ($end && $dl > $end) {
                    $e['registration_deadline'] = 'Registration deadline must be before the event ends.';
                }
                $d['registration_deadline_utc'] = $dl->setTimezone($utc)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                $e['registration_deadline'] = 'Enter a valid registration deadline.';
            }
        }

        // --- Pricing
        $d['pricing_type'] = in_array($s('pricing_type'), self::PRICING, true) ? $s('pricing_type') : 'unknown';
        $d['currency'] = in_array($s('currency'), self::CURRENCIES, true) ? $s('currency') : 'INR';
        $d['min_price'] = null;
        $d['max_price'] = null;
        if ($d['pricing_type'] === 'paid') {
            $min = filter_var($s('min_price'), FILTER_VALIDATE_FLOAT);
            $max = $s('max_price') === '' ? null : filter_var($s('max_price'), FILTER_VALIDATE_FLOAT);
            if ($min === false || $min <= 0 || $min > 10_000_000) {
                $e['min_price'] = 'Enter the minimum ticket price.';
            } elseif ($max === false || ($max !== null && ($max < $min || $max > 10_000_000))) {
                $e['max_price'] = 'Maximum price must be at least the minimum price.';
            } else {
                $d['min_price'] = round($min, 2);
                $d['max_price'] = $max !== null ? round($max, 2) : null;
            }
        }

        // --- Organizer
        $d['primary_organizer_id'] = null;
        if (!empty($in['use_organizer_profile'])) {
            $org = $this->organizerForUser($ownerId);
            if ($org) {
                $d['primary_organizer_id'] = (int) $org['id'];
            } elseif ($existing && $existing['primary_organizer_id']) {
                $d['primary_organizer_id'] = (int) $existing['primary_organizer_id']; // moderator editing
            }
        }
        $d['organizer_name']       = mb_substr(preg_replace('/\s+/', ' ', $s('organizer_name')), 0, 200);
        $d['organizer_email']      = $s('organizer_email');
        $d['organizer_phone']      = $s('organizer_phone');
        $d['organizer_website']    = $s('organizer_website');
        $d['organizer_social_url'] = $s('organizer_social_url');
        if (!$d['primary_organizer_id'] && mb_strlen($d['organizer_name']) < 2) $e['organizer_name'] = 'Enter the organizer name.';
        if (preg_match(self::HTML_TAG, $d['organizer_name'])) $e['organizer_name'] = 'HTML is not allowed.';
        if ($d['organizer_email'] !== '' && !filter_var($d['organizer_email'], FILTER_VALIDATE_EMAIL)) $e['organizer_email'] = 'Enter a valid email or leave it empty.';
        if ($d['organizer_phone'] !== '') {
            $digits = preg_replace('/\D/', '', $d['organizer_phone']);
            if (strlen($digits) < 8 || strlen($digits) > 15 || !preg_match('/^\+?[\d\s()-]+$/', $d['organizer_phone'])) {
                $e['organizer_phone'] = 'Enter a valid phone number or leave it empty.';
            } else {
                $d['organizer_phone'] = (str_starts_with($d['organizer_phone'], '+') ? '+' : '') . $digits;
            }
        }
        foreach (['organizer_website', 'organizer_social_url'] as $f) {
            if ($d[$f] !== '' && !UrlValidator::isAcceptable($d[$f])) $e[$f] = 'Enter a valid http(s) URL or leave it empty.';
        }

        return [$e, $d];
    }

    // ==================================================================
    // Internals
    // ==================================================================

    private function rateLimitError(int $userId): ?string
    {
        $cfg = Application::getInstance()->config('app.user_events', []);
        $row = $this->db->selectOne(
            "SELECT SUM(created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)) AS last_hour,
                    SUM(created_at > DATE_SUB(NOW(), INTERVAL 1 DAY))  AS last_day
               FROM events WHERE created_by_user_id = :uid AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)",
            [':uid' => $userId]
        );
        if ((int) ($row['last_hour'] ?? 0) >= (int) ($cfg['max_per_hour'] ?? 5)) {
            return 'You have posted several events in the last hour. Please wait a little before posting more.';
        }
        if ((int) ($row['last_day'] ?? 0) >= (int) ($cfg['max_per_day'] ?? 20)) {
            return 'You have reached the daily limit for posting events. Please try again tomorrow.';
        }
        return null;
    }

    /** @return array{block:bool, match:?array, score:int, signals:array} */
    private function checkDuplicate(array $d, ?int $excludeId): array
    {
        $match = $this->duplicates->findBestMatch([
            'title'            => $d['title'],
            'start_utc'        => $d['start_utc'],
            'district_id'      => $d['district_id'],
            'venue_name'       => $d['venue_name'] ?: null,
            'registration_url' => $d['registration_url'] ?: null,
            'organizer_name'   => $d['organizer_name'] ?: null,
        ], $excludeId);

        if (!$match) {
            return ['block' => false, 'match' => null, 'score' => 0, 'signals' => []];
        }
        $ev = $this->db->selectOne("SELECT id, slug, title, status FROM events WHERE id = :id", [':id' => $match['event_id']]);
        $info = ['event_id' => $match['event_id'], 'slug' => $ev['slug'] ?? '', 'title' => $ev['title'] ?? '', 'status' => $ev['status'] ?? '', 'score' => $match['score']];

        return [
            'block'   => $match['score'] >= $this->threshold('duplicate_merge_score'),
            'match'   => $info,
            'score'   => $match['score'],
            'signals' => $match['signals'],
        ];
    }

    /** @return array{0:string,1:string,2:?string} [status, moderation_status, reason] */
    private function decidePublication(int $ownerId, array $d, array $dup): array
    {
        $reasons = [];

        if ($dup['match'] && $dup['score'] >= $this->threshold('duplicate_review_score')) {
            $reasons[] = 'possible_duplicate_of_event_' . $dup['match']['event_id'];
        }

        $text = $d['title'] . "\n" . $d['short_summary'] . "\n" . $d['description'];
        foreach (self::SUSPICIOUS_PATTERNS as $p) {
            if (preg_match($p, $text)) {
                $reasons[] = 'suspicious_content';
                break;
            }
        }
        if (preg_match_all('#https?://#i', $d['description']) > 3) {
            $reasons[] = 'many_links_in_description';
        }
        $letters = preg_replace('/[^\p{L}]/u', '', $d['title']);
        if (mb_strlen($letters) >= 10 && mb_strtoupper($letters) === $letters && mb_strtolower($letters) !== $letters) {
            $reasons[] = 'all_caps_title';
        }
        if (preg_match('/(\p{L})\1{5,}/u', $text)) {
            $reasons[] = 'repeated_characters';
        }

        $history = $this->db->selectOne(
            "SELECT COUNT(*) AS n FROM events WHERE created_by_user_id = :uid AND (moderation_status IN ('rejected','suspended') OR status = 'rejected')",
            [':uid' => $ownerId]
        );
        if ((int) ($history['n'] ?? 0) >= 2) {
            $reasons[] = 'submitter_has_rejected_events';
        }

        if ($reasons) {
            return ['pending', 'needs_review', implode(',', array_unique($reasons))];
        }
        return ['published', 'clean', null];
    }

    public function reasonForHumans(?string $reason): string
    {
        if (!$reason) return '';
        $map = [
            'possible_duplicate'            => 'it may be a duplicate of an event already listed',
            'suspicious_content'            => 'some wording matched our spam filters',
            'many_links_in_description'     => 'the description contains many links',
            'all_caps_title'                => 'the title is written in capital letters',
            'repeated_characters'           => 'the text contains repeated characters',
            'submitter_has_rejected_events' => 'earlier submissions from this account needed moderation',
        ];
        $out = [];
        foreach (explode(',', $reason) as $r) {
            foreach ($map as $prefix => $label) {
                if (str_starts_with($r, $prefix)) $out[] = $label;
            }
        }
        return $out ? ucfirst(implode('; ', array_unique($out))) . '.' : 'It needs a quick manual check.';
    }

    private function eventParams(array $d): array
    {
        return [
            ':title' => $d['title'], ':ntitle' => $d['normalized_title'], ':summary' => $d['short_summary'],
            ':description' => $d['description'], ':org_id' => $d['primary_organizer_id'],
            ':org_name' => $d['organizer_name'] ?: null, ':org_email' => $d['organizer_email'] ?: null,
            ':org_phone' => $d['organizer_phone'] ?: null, ':org_web' => $d['organizer_website'] ?: null,
            ':org_social' => $d['organizer_social_url'] ?: null, ':format' => $d['format'],
            ':lang' => $d['primary_language'], ':reg_url' => $d['registration_url'] ?: null,
            ':reg_required' => $d['registration_required'], ':reg_deadline' => $d['registration_deadline_utc'],
            ':tz' => $d['timezone'], ':pricing' => $d['pricing_type'], ':currency' => $d['currency'],
            ':min_price' => $d['min_price'], ':max_price' => $d['max_price'], ':city_id' => $d['city_id'],
            ':online_platform' => $d['online_platform'] ?: null, ':online_url' => $d['online_url'] ?: null,
        ];
    }

    private function saveVenue(?int $venueId, array $d): ?int
    {
        if (!in_array($d['format'], ['offline', 'hybrid'], true)) {
            return null; // online event: venue detached (row kept if shared)
        }
        $params = [
            ':name' => $d['venue_name'], ':address' => $d['address'], ':locality' => $d['city_area'],
            ':city' => $d['city_id'], ':district' => $d['district_id'], ':postal' => $d['postal_code'] ?: null,
            ':lat' => $d['latitude'] !== '' ? (float) $d['latitude'] : null, ':lng' => $d['longitude'] !== '' ? (float) $d['longitude'] : null,
            ':map' => $d['map_url'] ?: null,
            ':state' => $d['district_id'] ? ((int) ($this->districts->findById((int) $d['district_id'])['state_id'] ?? 0) ?: null) : null,
        ];
        // Venues created by a submission belong to that event only, so updating in place is safe
        if ($venueId && $this->db->selectOne("SELECT COUNT(*) AS n FROM events WHERE venue_id = :v", [':v' => $venueId])['n'] <= 1) {
            $this->db->update(
                "UPDATE venues SET name = :name, address = :address, locality = :locality, city_id = :city, district_id = :district,
                        state_id = :state, postal_code = :postal, latitude = :lat, longitude = :lng, map_url = :map WHERE id = :id",
                $params + [':id' => $venueId]
            );
            return $venueId;
        }
        return $this->db->insert(
            "INSERT INTO venues (name, slug, address, locality, city_id, district_id, state_id, country_id, postal_code, latitude, longitude, map_url)
             VALUES (:name, :slug, :address, :locality, :city, :district, :state, 1, :postal, :lat, :lng, :map)",
            $params + [':slug' => 'venue-' . bin2hex(random_bytes(6))]
        );
    }

    private function saveOccurrence(int $eventId, array $d, bool $isNew): void
    {
        if ($isNew) {
            $this->db->insert(
                "INSERT INTO event_occurrences (event_id, start_at_utc, end_at_utc, timezone, status, registration_deadline)
                 VALUES (:e, :s, :end, :tz, 'scheduled', :dl)",
                [':e' => $eventId, ':s' => $d['start_utc'], ':end' => $d['end_utc'], ':tz' => $d['timezone'], ':dl' => $d['registration_deadline_utc']]
            );
            return;
        }
        $occ = $this->db->selectOne("SELECT id FROM event_occurrences WHERE event_id = :e ORDER BY start_at_utc ASC LIMIT 1", [':e' => $eventId]);
        if ($occ) {
            $this->db->update(
                "UPDATE event_occurrences SET start_at_utc = :s, end_at_utc = :end, timezone = :tz, registration_deadline = :dl,
                        status = IF(status = 'cancelled', status, 'scheduled') WHERE id = :id",
                [':s' => $d['start_utc'], ':end' => $d['end_utc'], ':tz' => $d['timezone'], ':dl' => $d['registration_deadline_utc'], ':id' => $occ['id']]
            );
        } else {
            $this->saveOccurrence($eventId, $d, true);
        }
    }

    private function saveCategoriesAndTags(int $eventId, array $d): void
    {
        $this->db->insert("INSERT INTO event_categories (event_id, category_id, is_primary) VALUES (:e, :c, 1)",
            [':e' => $eventId, ':c' => $d['primary_category_id']]);
        foreach ($d['additional_category_ids'] as $cid) {
            $this->db->statement("INSERT IGNORE INTO event_categories (event_id, category_id, is_primary) VALUES (:e, :c, 0)",
                [':e' => $eventId, ':c' => $cid]);
        }
        foreach ($d['tags'] as $slugBase => $name) {
            $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $slugBase), '-') ?: substr(md5($slugBase), 0, 12);
            $tag = $this->db->selectOne("SELECT id FROM tags WHERE slug = :s", [':s' => $slug]);
            $tagId = $tag ? (int) $tag['id'] : $this->db->insert("INSERT INTO tags (name, slug) VALUES (:n, :s)", [':n' => mb_substr($name, 0, 100), ':s' => $slug]);
            $this->db->statement("INSERT IGNORE INTO event_tags (event_id, tag_id) VALUES (:e, :t)", [':e' => $eventId, ':t' => $tagId]);
            $this->db->update("UPDATE tags SET usage_count = usage_count + 1 WHERE id = :id", [':id' => $tagId]);
        }
    }

    /** City row for the typed city/area inside the chosen district, else the district's main city row. */
    private function resolveCityId(int $districtId, string $cityArea): ?int
    {
        $norm = mb_strtolower(trim($cityArea));
        if ($norm !== '') {
            $row = $this->db->selectOne(
                "SELECT id FROM cities WHERE district_id = :d AND is_active = 1 AND LOWER(name) = :n LIMIT 1",
                [':d' => $districtId, ':n' => $norm]
            ) ?: $this->db->selectOne(
                "SELECT c.id FROM areas a JOIN cities c ON c.id = a.city_id
                  WHERE c.district_id = :d AND a.is_active = 1 AND LOWER(a.name) = :n LIMIT 1",
                [':d' => $districtId, ':n' => $norm]
            );
            if ($row) return (int) $row['id'];
        }
        $row = $this->db->selectOne(
            "SELECT id FROM cities WHERE district_id = :d AND is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 1",
            [':d' => $districtId]
        );
        return $row ? (int) $row['id'] : null;
    }

    private function parseLocal(string $date, string $time, \DateTimeZone $tz): ?\DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', "{$date} {$time}", $tz);
        $errs = \DateTimeImmutable::getLastErrors();
        if (!$dt || ($errs && ($errs['warning_count'] || $errs['error_count'])) || $dt->format('Y-m-d H:i') !== "{$date} {$time}") {
            return null; // rejects 2026-02-30 etc.
        }
        return $dt;
    }

    private function firstStartUtc(int $eventId): ?string
    {
        $row = $this->db->selectOne("SELECT MIN(start_at_utc) AS s FROM event_occurrences WHERE event_id = :e", [':e' => $eventId]);
        return $row['s'] ?? null;
    }

    private function meaningfulChanges(array $before, array $after): array
    {
        $labels = [
            'start_date' => 'date', 'start_time' => 'start time', 'end_date' => 'end date', 'end_time' => 'end time',
            'venue_name' => 'venue', 'address' => 'address', 'district_id' => 'district', 'format' => 'format',
            'registration_url' => 'registration link', 'online_url' => 'online link',
        ];
        $changed = [];
        foreach ($labels as $k => $label) {
            if ((string) ($before[$k] ?? '') !== (string) ($after[$k] ?? '')) {
                $changed[$label] = ucfirst($label) . ' changed';
            }
        }
        return array_values($changed);
    }

    private function uniqueSlug(string $title): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($title)), '-') ?: 'event';
        $base = mb_substr($base, 0, 80);
        $slug = $base;
        $i = 1;
        while ($this->db->selectOne('SELECT id FROM events WHERE slug = :s', [':s' => $slug])) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }

    private function threshold(string $key): int
    {
        return (int) Application::getInstance()->config('app.discovery.' . $key, $key === 'duplicate_merge_score' ? 85 : 60);
    }
}
