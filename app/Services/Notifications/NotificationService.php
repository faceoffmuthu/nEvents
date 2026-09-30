<?php

declare(strict_types=1);

namespace NEvents\Services\Notifications;

use NEvents\Core\Application;
use NEvents\Core\Database\Connection;
use NEvents\Core\Log;
use NEvents\Core\View;
use NEvents\Services\Mail\MailService;

/**
 * Email-only notification system (WhatsApp is not a channel).
 *
 * Web requests only ever QUEUE rows in notification_jobs (cheap INSERTs).
 * Actual SMTP delivery happens in deliverDue(), run by bin/scheduler.php
 * (task "send-emails") or bin/worker.php — never inside an HTTP request.
 *
 * notification_jobs.fingerprint is UNIQUE, so re-running a scheduler task
 * can never queue the same digest/reminder twice.
 */
class NotificationService
{
    public const REMINDER_KINDS = ['reminder_tomorrow', 'reminder_soon', 'registration_closing'];

    private const MAX_RETRIES = 3;

    public function __construct(
        private Connection  $db,
        private MailService $mail,
    ) {}

    // ------------------------------------------------------------------
    // Queueing
    // ------------------------------------------------------------------

    /** @return bool true when a new job was queued (false = fingerprint already exists) */
    public function queue(int $userId, string $kind, string $subject, array $payload, ?int $eventId = null,
                          ?string $fingerprint = null, ?string $scheduledAt = null): bool
    {
        $affected = $this->db->update(
            "INSERT IGNORE INTO notification_jobs
                (user_id, event_id, kind, channel, subject, payload_json, fingerprint, status, scheduled_at)
             VALUES (:uid, :eid, :kind, 'email', :subject, :payload, :fp, 'queued', :at)",
            [
                ':uid'     => $userId,
                ':eid'     => $eventId,
                ':kind'    => $kind,
                ':subject' => mb_substr($subject, 0, 300),
                ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':fp'      => $fingerprint ? hash('sha256', $fingerprint) : null,
                ':at'      => $scheduledAt ?? date('Y-m-d H:i:s'),
            ]
        );
        return $affected > 0;
    }

    /** Cancels not-yet-sent jobs for an event (e.g. reminders after a reschedule/cancel). */
    public function cancelQueuedForEvent(int $eventId, array $kinds = self::REMINDER_KINDS): int
    {
        if (!$kinds) return 0;
        $in = implode(',', array_fill(0, count($kinds), '?'));
        return $this->db->update(
            "UPDATE notification_jobs SET status = 'cancelled' WHERE status = 'queued' AND event_id = ? AND kind IN ({$in})",
            array_merge([$eventId], $kinds)
        );
    }

    /**
     * Daily / weekly digest of upcoming events in the user's district that
     * match their interests. Respects email_enabled, daily/weekly_digest,
     * digest_time (IST) and digest_day (1 = Monday) for weekly.
     */
    public function queueDigests(string $cadence, ?\DateTimeImmutable $nowIst = null): int
    {
        $nowIst ??= new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata'));
        $col    = $cadence === 'weekly' ? 'weekly_digest' : 'daily_digest';
        $days   = $cadence === 'weekly' ? 14 : 7;
        $limit  = $this->maxDigestEvents();

        $users = $this->db->select(
            "SELECT u.id, u.name, u.email, np.digest_time, np.digest_day,
                    (SELECT COALESCE(ul.district_id, c.district_id)
                       FROM user_locations ul LEFT JOIN cities c ON c.id = ul.city_id
                      WHERE ul.user_id = u.id AND ul.is_primary = 1 LIMIT 1) AS district_id
               FROM users u
               JOIN notification_preferences np ON np.user_id = u.id
              WHERE u.status = 'active' AND u.email_verified_at IS NOT NULL
                AND np.email_enabled = 1 AND np.{$col} = 1"
        );

        $queued = 0;
        foreach ($users as $u) {
            if ($nowIst->format('H:i:s') < (string) $u['digest_time']) {
                continue;
            }
            if ($cadence === 'weekly' && (int) $nowIst->format('N') !== (int) ($u['digest_day'] ?: 1)) {
                continue;
            }

            $events = $this->digestEvents((int) $u['id'], $u['district_id'] ? (int) $u['district_id'] : null, $days, $limit);
            if (!$events) {
                continue; // never send an empty digest
            }

            $periodKey = $cadence === 'weekly' ? $nowIst->format('o-\WW') : $nowIst->format('Y-m-d');
            $subject   = $cadence === 'weekly' ? 'Your weekly event roundup' : "Today's picks: events near you";
            $ok = $this->queue((int) $u['id'], "digest_{$cadence}", $subject, [
                'heading' => $cadence === 'weekly' ? 'Events coming up' : 'Events picked for you',
                'intro'   => $cadence === 'weekly'
                    ? "here's what's happening over the next two weeks."
                    : "here are upcoming events that match your interests.",
                'events'  => array_map([$this, 'summarize'], $events),
                'cta'     => ['label' => 'Discover more events', 'url' => View::url('discover')],
            ], null, "digest|{$cadence}|{$u['id']}|{$periodKey}");
            $queued += $ok ? 1 : 0;
        }
        return $queued;
    }

    /**
     * Saved-event reminders: happening tomorrow (~24h), happening soon (~2h),
     * registration closing within 24h. Fingerprints include the occurrence
     * start time, so a rescheduled event gets fresh reminders.
     */
    public function queueSavedEventReminders(): int
    {
        $queued = 0;
        $windows = [
            'reminder_tomorrow' => ['pref' => 'reminder_24h', 'from' => 20 * 60, 'to' => 28 * 60, 'field' => 'eo.start_at_utc',
                                    'subject' => 'Tomorrow: %s', 'heading' => 'Happening tomorrow', 'intro' => 'an event you saved starts tomorrow.'],
            'reminder_soon'     => ['pref' => 'reminder_2h',  'from' => 0,       'to' => 3 * 60,  'field' => 'eo.start_at_utc',
                                    'subject' => 'Starting soon: %s', 'heading' => 'Starting soon', 'intro' => 'an event you saved starts in a couple of hours.'],
            'registration_closing' => ['pref' => 'reminder_24h', 'from' => 0, 'to' => 24 * 60, 'field' => 'COALESCE(eo.registration_deadline, e.registration_deadline)',
                                    'subject' => 'Registration closing: %s', 'heading' => 'Registration closes soon', 'intro' => 'registration for an event you saved closes within 24 hours.'],
        ];

        foreach ($windows as $kind => $w) {
            $rows = $this->db->select(
                "SELECT u.id AS user_id, u.name AS user_name, e.*, eo.id AS occurrence_id,
                        eo.start_at_utc AS next_start, {$w['field']} AS trigger_at,
                        v.name AS venue_name, c.name AS city_name
                   FROM saved_events se
                   JOIN users u ON u.id = se.user_id AND u.status = 'active' AND u.email_verified_at IS NOT NULL
                   JOIN notification_preferences np ON np.user_id = u.id AND np.email_enabled = 1 AND np.{$w['pref']} = 1
                   JOIN events e ON e.id = se.event_id AND e.status = 'published'
                   JOIN event_occurrences eo ON eo.event_id = e.id AND eo.status = 'scheduled'
                   LEFT JOIN venues v ON v.id = e.venue_id
                   LEFT JOIN cities c ON c.id = e.city_id
                  WHERE {$w['field']} > DATE_ADD(UTC_TIMESTAMP(), INTERVAL {$w['from']} MINUTE)
                    AND {$w['field']} <= DATE_ADD(UTC_TIMESTAMP(), INTERVAL {$w['to']} MINUTE)"
                    . ($kind === 'registration_closing' ? " AND e.registration_url IS NOT NULL AND eo.start_at_utc > UTC_TIMESTAMP()" : '')
            );

            foreach ($rows as $r) {
                $ok = $this->queue((int) $r['user_id'], $kind, sprintf($w['subject'], $r['title']), [
                    'heading' => $w['heading'],
                    'intro'   => $w['intro'],
                    'events'  => [$this->summarize($r)],
                    'cta'     => ['label' => 'View event', 'url' => View::url('event/' . $r['slug'])],
                ], (int) $r['id'], "{$kind}|{$r['user_id']}|{$r['id']}|{$r['trigger_at']}");
                $queued += $ok ? 1 : 0;
            }
        }
        return $queued;
    }

    /**
     * Tells users who saved an event that it changed. $change is one of
     * updated | postponed | cancelled. Only for meaningful changes — callers
     * decide what counts (date/time/venue/district/format/registration link).
     */
    public function notifySaversOfChange(int $eventId, string $change, string $summary): int
    {
        $event = $this->loadEvent($eventId);
        if (!$event) return 0;

        $labels = [
            'updated'   => ['Event updated: %s',   'An event you saved was updated', 'an event you saved has changed: '],
            'postponed' => ['Event postponed: %s', 'An event you saved was postponed', 'an event you saved has been postponed. '],
            'cancelled' => ['Event cancelled: %s', 'An event you saved was cancelled', 'an event you saved has been cancelled by its organizer. '],
        ];
        [$subject, $heading, $intro] = $labels[$change] ?? $labels['updated'];

        $savers = $this->db->select(
            "SELECT u.id FROM saved_events se
               JOIN users u ON u.id = se.user_id AND u.status = 'active' AND u.email_verified_at IS NOT NULL
               JOIN notification_preferences np ON np.user_id = u.id AND np.email_enabled = 1 AND np.event_updates = 1
              WHERE se.event_id = :eid",
            [':eid' => $eventId]
        );

        $queued = 0;
        foreach ($savers as $s) {
            $ok = $this->queue((int) $s['id'], "event_{$change}", sprintf($subject, $event['title']), [
                'heading' => $heading,
                'intro'   => $intro . $summary,
                'events'  => [$this->summarize($event)],
                'cta'     => ['label' => 'View event', 'url' => View::url('event/' . $event['slug'])],
            ], $eventId, "change|{$change}|{$eventId}|{$s['id']}|" . md5($summary));
            $queued += $ok ? 1 : 0;
        }
        return $queued;
    }

    /**
     * Status emails to the person who posted a community event (published,
     * held for review, unpublished/rejected by moderation). These are about
     * the user's own content, so they're sent regardless of digest settings.
     */
    public function notifyOwner(int $eventId, string $kind, string $reason = ''): bool
    {
        $event = $this->loadEvent($eventId);
        if (!$event || empty($event['created_by_user_id'])) return false;

        $map = [
            'published'    => ['Your event is live: %s', 'Your event has been published', 'your event is now live and visible in event listings.', 'View your event', 'event/' . $event['slug']],
            'needs_review' => ['Your event is being reviewed: %s', 'Your event is under review', 'your event was saved but held for a quick review before it appears in listings. ', 'Go to My Events', 'my-events'],
            'unpublished'  => ['Your event was unpublished: %s', 'Your event was unpublished', 'a moderator unpublished your event. ', 'Go to My Events', 'my-events'],
            'rejected'     => ['Your event was not approved: %s', 'Your event was not approved', 'a moderator rejected your event. ', 'Go to My Events', 'my-events'],
        ];
        if (!isset($map[$kind])) return false;
        [$subject, $heading, $intro, $ctaLabel, $ctaPath] = $map[$kind];

        return $this->queue((int) $event['created_by_user_id'], "owner_{$kind}", sprintf($subject, $event['title']), [
            'heading'    => $heading,
            'intro'      => $intro . ($reason !== '' ? 'Reason: ' . $reason : ''),
            'events'     => [$this->summarize($event)],
            'cta'        => ['label' => $ctaLabel, 'url' => View::url($ctaPath)],
            'footerNote' => $kind === 'published' ? 'You can edit or cancel it any time from My Events.' : '',
        ], $eventId, "owner|{$kind}|{$eventId}|" . date('Y-m-d H'));
    }

    // ------------------------------------------------------------------
    // Delivery (background only)
    // ------------------------------------------------------------------

    /** @return array{sent:int, failed:int, retried:int} */
    public function deliverDue(int $limit = 50): array
    {
        $stats = ['sent' => 0, 'failed' => 0, 'retried' => 0];
        $appName  = (string) Application::getInstance()->config('app.name', 'N Events');
        $prefsUrl = View::url('account/preferences');

        $jobs = $this->db->select(
            "SELECT nj.*, u.name, u.email, u.status AS user_status
               FROM notification_jobs nj JOIN users u ON u.id = nj.user_id
              WHERE nj.status = 'queued' AND nj.channel = 'email' AND nj.scheduled_at <= NOW()
              ORDER BY nj.id ASC LIMIT :limit",
            [':limit' => $limit]
        );

        foreach ($jobs as $job) {
            // Claim — another runner may have picked it up
            if ($this->db->update("UPDATE notification_jobs SET status = 'sending' WHERE id = :id AND status = 'queued'", [':id' => $job['id']]) === 0) {
                continue;
            }
            if ($job['user_status'] !== 'active') {
                $this->db->update("UPDATE notification_jobs SET status = 'cancelled' WHERE id = :id", [':id' => $job['id']]);
                continue;
            }

            $payload = json_decode((string) $job['payload_json'], true) ?: [];
            $data = [
                'appName'    => $appName,
                'name'       => $job['name'],
                'heading'    => $payload['heading'] ?? $job['subject'],
                'intro'      => $payload['intro'] ?? '',
                'events'     => $payload['events'] ?? [],
                'cta'        => $payload['cta'] ?? null,
                'footerNote' => $payload['footerNote'] ?? '',
                'prefsUrl'   => $prefsUrl,
            ];
            $html = (new View())->render('emails.notification', $data);
            $text = $this->plainText($data);

            $ok = $this->mail->sendNotification($job['email'], $job['name'], (string) $job['subject'], $html, $text);

            if ($ok) {
                $this->db->update("UPDATE notification_jobs SET status = 'sent', sent_at = NOW(), error_message = NULL WHERE id = :id", [':id' => $job['id']]);
                $stats['sent']++;
            } elseif ((int) $job['retry_count'] + 1 < self::MAX_RETRIES) {
                $this->db->update(
                    "UPDATE notification_jobs SET status = 'queued', retry_count = retry_count + 1,
                            scheduled_at = DATE_ADD(NOW(), INTERVAL :mins MINUTE), error_message = 'send failed — see app log'
                      WHERE id = :id",
                    [':id' => $job['id'], ':mins' => 5 * (2 ** (int) $job['retry_count'])]
                );
                $stats['retried']++;
            } else {
                $this->db->update("UPDATE notification_jobs SET status = 'failed', retry_count = retry_count + 1, error_message = 'send failed — see app log' WHERE id = :id", [':id' => $job['id']]);
                $stats['failed']++;
            }

            $this->db->insert(
                "INSERT INTO notification_deliveries (notification_job_id, user_id, channel, status, sent_at, error_message)
                 VALUES (:jid, :uid, 'email', :status, NOW(), :err)",
                [':jid' => $job['id'], ':uid' => $job['user_id'], ':status' => $ok ? 'sent' : 'failed', ':err' => $ok ? null : 'smtp_failure']
            );
        }

        if ($stats['sent'] || $stats['failed']) {
            Log::get()->info('notification_batch', $stats);
        }
        return $stats;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function digestEvents(int $userId, ?int $districtId, int $days, int $limit): array
    {
        $params = [':uid' => $userId, ':days' => $days, ':limit' => $limit];
        $districtSql = '';
        if ($districtId) {
            $districtSql = ' AND c.district_id = :did';
            $params[':did'] = $districtId;
        }
        // Prefer events matching the user's interests (category or its parent);
        // users with no saved interests get everything in their district.
        return $this->db->select(
            "SELECT e.*, MIN(eo.start_at_utc) AS next_start, v.name AS venue_name, c.name AS city_name
               FROM events e
               JOIN event_occurrences eo ON eo.event_id = e.id AND eo.status = 'scheduled'
                    AND eo.start_at_utc >= UTC_TIMESTAMP()
                    AND eo.start_at_utc < DATE_ADD(UTC_TIMESTAMP(), INTERVAL :days DAY)
               LEFT JOIN cities c ON c.id = e.city_id
               LEFT JOIN venues v ON v.id = e.venue_id
              WHERE e.status = 'published' AND e.data_origin NOT IN ('demo','seed') {$districtSql}
                AND e.id NOT IN (SELECT event_id FROM hidden_events WHERE user_id = :uid)
                AND (
                  NOT EXISTS (SELECT 1 FROM user_interests ui WHERE ui.user_id = :uid2)
                  OR EXISTS (SELECT 1 FROM event_categories ec JOIN categories cat ON cat.id = ec.category_id
                              JOIN user_interests ui ON ui.user_id = :uid3 AND (ui.category_id = cat.id OR ui.category_id = cat.parent_id)
                             WHERE ec.event_id = e.id)
                )
              GROUP BY e.id
              ORDER BY next_start ASC
              LIMIT :limit",
            $params + [':uid2' => $userId, ':uid3' => $userId]
        );
    }

    private function loadEvent(int $eventId): array|false
    {
        return $this->db->selectOne(
            "SELECT e.*, (SELECT MIN(start_at_utc) FROM event_occurrences WHERE event_id = e.id AND status IN ('scheduled','postponed')) AS next_start,
                    v.name AS venue_name, c.name AS city_name
               FROM events e LEFT JOIN venues v ON v.id = e.venue_id LEFT JOIN cities c ON c.id = e.city_id
              WHERE e.id = :id",
            [':id' => $eventId]
        );
    }

    /** Compact, pre-formatted event block for the email template. */
    public function summarize(array $e): array
    {
        $when = '';
        if (!empty($e['next_start'])) {
            $when = (new \DateTimeImmutable($e['next_start'], new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone('Asia/Kolkata'))->format('D, d M Y · h:i A') . ' IST';
        }
        $where = ($e['format'] ?? '') === 'online'
            ? 'Online'
            : trim(implode(', ', array_filter([$e['venue_name'] ?? null, $e['city_name'] ?? null])));
        $price = match ($e['pricing_type'] ?? 'unknown') {
            'free'     => 'Free',
            'paid'     => !empty($e['min_price']) ? '₹' . number_format((float) $e['min_price'], 0) . ' onwards' : 'Paid',
            'donation' => 'Donation',
            default    => '',
        };
        return ['title' => $e['title'], 'url' => View::url('event/' . $e['slug']), 'when' => $when, 'where' => $where, 'price' => $price];
    }

    private function plainText(array $d): string
    {
        $lines = ["Hi {$d['name']}, {$d['intro']}", ''];
        foreach ($d['events'] as $ev) {
            $lines[] = '- ' . $ev['title'];
            foreach (['when', 'where', 'price'] as $k) {
                if (!empty($ev[$k])) $lines[] = '  ' . $ev[$k];
            }
            $lines[] = '  ' . $ev['url'];
        }
        if (!empty($d['cta']['url'])) {
            $lines[] = '';
            $lines[] = ($d['cta']['label'] ?? 'Open') . ': ' . $d['cta']['url'];
        }
        if (!empty($d['footerNote'])) $lines[] = "\n" . $d['footerNote'];
        $lines[] = "\nChange email preferences: {$d['prefsUrl']}";
        return implode("\n", $lines);
    }

    private function maxDigestEvents(): int
    {
        $row = $this->db->selectOne("SELECT value FROM system_settings WHERE key_name = 'max_digest_events'");
        return max(1, (int) ($row['value'] ?? 10));
    }
}
