<?php

declare(strict_types=1);

namespace NEvents\Services\Moderation;

use NEvents\Core\Application;
use NEvents\Core\Database\Connection;
use NEvents\Services\Audit\AuditLogger;
use NEvents\Services\Notifications\NotificationService;

/**
 * Admin/moderator actions on events, submitters, reports and duplicate
 * pairs. Every state change is written to moderation_actions + audit_logs.
 */
class ModerationService
{
    public const REPORT_REASONS = [
        'fake'           => 'Fake event',
        'spam'           => 'Spam',
        'incorrect_info' => 'Wrong information',
        'cancelled'      => 'Event is cancelled',
        'wrong_datetime' => 'Incorrect date/time',
        'wrong_venue'    => 'Incorrect venue',
        'broken_link'    => 'Broken registration link',
        'duplicate'      => 'Duplicate event',
        'inappropriate'  => 'Inappropriate content',
        'scam'           => 'Scam / fraud',
    ];

    /** Distinct open reports that automatically flag an event for moderator attention. */
    private const AUTO_FLAG_REPORTS = 3;

    public function __construct(
        private Connection          $db,
        private AuditLogger         $audit,
        private NotificationService $notifications,
    ) {}

    // ------------------------------------------------------------------
    // Event actions
    // ------------------------------------------------------------------

    public function apply(string $action, int $eventId, int $actorId, string $reason = ''): array
    {
        $event = $this->db->selectOne("SELECT * FROM events WHERE id = :id", [':id' => $eventId]);
        if (!$event) {
            return ['ok' => false, 'error' => 'Event not found.'];
        }
        $reason = trim(mb_substr($reason, 0, 400));

        [$set, $ownerNotice] = match ($action) {
            'publish'   => [["status = 'published'", "moderation_status = 'clean'", 'moderation_reason = NULL',
                             'published_at = NOW()', 'first_published_at = COALESCE(first_published_at, NOW())'], 'published'],
            'unpublish' => [["status = 'pending'", "moderation_status = 'needs_review'", 'moderation_reason = :reason'], 'unpublished'],
            'flag'      => [["moderation_status = 'flagged'", 'moderation_reason = :reason'], null],
            'unflag'    => [["moderation_status = 'clean'", 'moderation_reason = NULL'], null],
            'reject'    => [["status = 'rejected'", "moderation_status = 'rejected'", 'moderation_reason = :reason'], 'rejected'],
            'suspend'   => [["status = 'archived'", "moderation_status = 'suspended'", 'moderation_reason = :reason'], 'unpublished'],
            'cancel'    => [["status = 'cancelled'"], null],
            default     => [null, null],
        };
        if ($set === null) {
            return ['ok' => false, 'error' => 'Unknown action.'];
        }

        $params = [':id' => $eventId];
        if (str_contains(implode(',', $set), ':reason')) {
            $params[':reason'] = 'moderator: ' . ($reason !== '' ? $reason : $action);
        }
        $this->db->update('UPDATE events SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE id = :id', $params);

        if ($action === 'cancel') {
            $this->db->update("UPDATE event_occurrences SET status = 'cancelled' WHERE event_id = :id AND status IN ('scheduled','postponed')", [':id' => $eventId]);
            $this->notifications->cancelQueuedForEvent($eventId);
            if ($event['status'] === 'published') {
                $this->notifications->notifySaversOfChange($eventId, 'cancelled', $reason);
            }
        } elseif (in_array($action, ['unpublish', 'reject', 'suspend'], true)) {
            $this->notifications->cancelQueuedForEvent($eventId);
        }

        if ($ownerNotice && $event['created_by_user_id'] && (int) $event['created_by_user_id'] !== $actorId) {
            $this->notifications->notifyOwner($eventId, $ownerNotice, $reason);
        }

        $after = $this->db->selectOne("SELECT status, moderation_status, moderation_reason FROM events WHERE id = :id", [':id' => $eventId]);
        $this->audit->moderation($actorId, 'event.' . $action, 'event', $eventId,
            ['status' => $event['status'], 'moderation_status' => $event['moderation_status']], $after ?: null, $reason);

        return ['ok' => true];
    }

    public function setSubmitterPosting(int $userId, bool $allowed, int $actorId, string $reason = ''): array
    {
        $user = $this->db->selectOne("SELECT id, can_post_events FROM users WHERE id = :id", [':id' => $userId]);
        if (!$user) {
            return ['ok' => false, 'error' => 'User not found.'];
        }
        $this->db->update("UPDATE users SET can_post_events = :a WHERE id = :id", [':a' => $allowed ? 1 : 0, ':id' => $userId]);
        $this->audit->moderation($actorId, $allowed ? 'user.reinstate_posting' : 'user.suspend_posting', 'user', $userId,
            ['can_post_events' => (int) $user['can_post_events']], ['can_post_events' => $allowed ? 1 : 0], $reason);
        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Reports
    // ------------------------------------------------------------------

    public function report(int $eventId, int $userId, string $reason, string $description, string $ip): array
    {
        if (!isset(self::REPORT_REASONS[$reason])) {
            return ['ok' => false, 'error' => 'Choose a reason for the report.'];
        }
        $description = trim(strip_tags($description));
        if (mb_strlen($description) > 1000) {
            return ['ok' => false, 'error' => 'Please keep details under 1000 characters.'];
        }

        $already = $this->db->selectOne(
            "SELECT id FROM reports WHERE event_id = :e AND user_id = :u AND status IN ('open','reviewing') LIMIT 1",
            [':e' => $eventId, ':u' => $userId]
        );
        if ($already) {
            return ['ok' => true, 'message' => 'You have already reported this event — our team is reviewing it.'];
        }

        $max = (int) Application::getInstance()->config('app.user_events.max_reports_per_day', 10);
        $today = $this->db->selectOne(
            "SELECT COUNT(*) AS n FROM reports WHERE user_id = :u AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)",
            [':u' => $userId]
        );
        if ((int) $today['n'] >= $max) {
            return ['ok' => false, 'error' => 'You have sent many reports today. Please try again tomorrow.'];
        }

        $reportId = $this->db->insert(
            "INSERT INTO reports (event_id, user_id, reason, description, status, ip_address)
             VALUES (:e, :u, :r, :d, 'open', :ip)",
            [':e' => $eventId, ':u' => $userId, ':r' => $reason, ':d' => $description ?: null, ':ip' => $ip]
        );
        $this->audit->log($userId, 'report.create', 'event', $eventId, "Event reported: {$reason}", ['report_id' => $reportId]);

        // Several independent reports -> flag for moderators (stays visible until reviewed)
        $open = $this->db->selectOne(
            "SELECT COUNT(DISTINCT user_id) AS n FROM reports WHERE event_id = :e AND status IN ('open','reviewing')",
            [':e' => $eventId]
        );
        if ((int) $open['n'] >= self::AUTO_FLAG_REPORTS) {
            $flagged = $this->db->update(
                "UPDATE events SET moderation_status = 'flagged', moderation_reason = :r WHERE id = :id AND moderation_status = 'clean'",
                [':id' => $eventId, ':r' => 'auto: ' . $open['n'] . ' open user reports']
            );
            if ($flagged) {
                $this->audit->log(null, 'event.auto_flag', 'event', $eventId, 'Auto-flagged after multiple user reports', [], 'system');
            }
        }

        return ['ok' => true, 'message' => 'Thanks — your report was sent to our moderation team.'];
    }

    public function resolveReport(int $reportId, int $actorId, string $status, string $resolution): array
    {
        if (!in_array($status, ['resolved', 'dismissed', 'reviewing'], true)) {
            return ['ok' => false, 'error' => 'Invalid report status.'];
        }
        $report = $this->db->selectOne("SELECT * FROM reports WHERE id = :id", [':id' => $reportId]);
        if (!$report) {
            return ['ok' => false, 'error' => 'Report not found.'];
        }
        $this->db->update(
            "UPDATE reports SET status = :s, resolution = :res, reviewed_by = :a, reviewed_at = NOW() WHERE id = :id",
            [':s' => $status, ':res' => mb_substr($resolution, 0, 200) ?: null, ':a' => $actorId, ':id' => $reportId]
        );
        $this->audit->moderation($actorId, 'report.' . $status, 'report', $reportId, ['status' => $report['status']], ['status' => $status], $resolution);
        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Duplicates
    // ------------------------------------------------------------------

    /**
     * Merges $mergedId into $survivorId: source references, saves and reports
     * move to the survivor, the merged event is archived (never deleted, so a
     * community submitter's ownership record is preserved).
     */
    public function mergeDuplicate(int $candidateId, int $survivorId, int $actorId): array
    {
        $pair = $this->db->selectOne("SELECT * FROM duplicate_candidates WHERE id = :id AND status = 'pending'", [':id' => $candidateId]);
        if (!$pair || !in_array($survivorId, [(int) $pair['event_a_id'], (int) $pair['event_b_id']], true)) {
            return ['ok' => false, 'error' => 'Duplicate pair not found or already handled.'];
        }
        $mergedId = $survivorId === (int) $pair['event_a_id'] ? (int) $pair['event_b_id'] : (int) $pair['event_a_id'];
        $merged   = $this->db->selectOne("SELECT * FROM events WHERE id = :id", [':id' => $mergedId]);

        $this->db->transaction(function () use ($survivorId, $mergedId, $merged, $candidateId, $actorId) {
            $this->db->update("UPDATE event_sources SET event_id = :s, is_primary = 0 WHERE event_id = :m", [':s' => $survivorId, ':m' => $mergedId]);
            $this->db->statement("INSERT IGNORE INTO saved_events (user_id, event_id, created_at) SELECT user_id, :s, created_at FROM saved_events WHERE event_id = :m",
                [':s' => $survivorId, ':m' => $mergedId]);
            $this->db->delete("DELETE FROM saved_events WHERE event_id = :m", [':m' => $mergedId]);
            $this->db->update("UPDATE reports SET event_id = :s WHERE event_id = :m", [':s' => $survivorId, ':m' => $mergedId]);
            $this->db->update(
                "UPDATE events SET status = 'archived', moderation_reason = :r WHERE id = :m",
                [':r' => "merged_into_event_{$survivorId}", ':m' => $mergedId]
            );
            $this->db->update(
                "UPDATE events SET save_count = (SELECT COUNT(*) FROM saved_events WHERE event_id = :s2) WHERE id = :s",
                [':s' => $survivorId, ':s2' => $survivorId]
            );
            $this->db->insert(
                "INSERT INTO event_merge_history (survivor_id, merged_id, merged_by, merge_reason, snapshot_json)
                 VALUES (:s, :m, :a, 'duplicate_review', :snap)",
                [':s' => $survivorId, ':m' => $mergedId, ':a' => $actorId, ':snap' => json_encode($merged, JSON_UNESCAPED_UNICODE)]
            );
            $this->db->update("UPDATE duplicate_candidates SET status = 'merged', reviewed_by = :a, reviewed_at = NOW() WHERE id = :id",
                [':a' => $actorId, ':id' => $candidateId]);
        });

        $this->audit->moderation($actorId, 'event.merge', 'event', $survivorId, ['merged_id' => $mergedId], ['survivor_id' => $survivorId], "Merged #{$mergedId} into #{$survivorId}");
        return ['ok' => true];
    }

    public function dismissDuplicate(int $candidateId, int $actorId): array
    {
        $n = $this->db->update(
            "UPDATE duplicate_candidates SET status = 'rejected', reviewed_by = :a, reviewed_at = NOW() WHERE id = :id AND status = 'pending'",
            [':a' => $actorId, ':id' => $candidateId]
        );
        if ($n) {
            $this->audit->moderation($actorId, 'duplicate.dismiss', 'duplicate_candidate', $candidateId, null, ['status' => 'rejected'], 'Not a duplicate');
        }
        return ['ok' => (bool) $n];
    }
}
