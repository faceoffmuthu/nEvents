<?php

declare(strict_types=1);

namespace NEvents\Services\Audit;

use NEvents\Core\Database\Connection;
use NEvents\Core\Log;

/**
 * Writes audit_logs rows (and moderation_actions for moderator decisions).
 * Never throws — an audit write failure must not break the user action, but
 * it is logged so it can't fail silently.
 */
class AuditLogger
{
    public function __construct(private Connection $db) {}

    public function log(?int $actorId, string $action, string $entityType, ?int $entityId, string $summary, array $meta = [], string $actorType = 'user'): void
    {
        try {
            $this->db->insert(
                "INSERT INTO audit_logs (actor_id, actor_type, action, entity_type, entity_id, summary, meta_json, ip_address)
                 VALUES (:actor, :type, :action, :etype, :eid, :summary, :meta, :ip)",
                [
                    ':actor'   => $actorId,
                    ':type'    => $actorType,
                    ':action'  => $action,
                    ':etype'   => $entityType,
                    ':eid'     => $entityId,
                    ':summary' => mb_substr($summary, 0, 500),
                    ':meta'    => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                    ':ip'      => PHP_SAPI === 'cli' ? null : ($_SERVER['REMOTE_ADDR'] ?? null),
                ]
            );
        } catch (\Throwable $e) {
            Log::get()->error('audit_log_failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }

    public function moderation(int $actorId, string $action, string $entityType, int $entityId, ?array $before, ?array $after, string $notes = ''): void
    {
        try {
            $this->db->insert(
                "INSERT INTO moderation_actions (actor_id, action, entity_type, entity_id, before_json, after_json, notes, ip_address)
                 VALUES (:actor, :action, :etype, :eid, :before, :after, :notes, :ip)",
                [
                    ':actor'  => $actorId,
                    ':action' => $action,
                    ':etype'  => $entityType,
                    ':eid'    => $entityId,
                    ':before' => $before !== null ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
                    ':after'  => $after !== null ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
                    ':notes'  => $notes,
                    ':ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
                ]
            );
        } catch (\Throwable $e) {
            Log::get()->error('moderation_log_failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
        $this->log($actorId, 'moderation.' . $action, $entityType, $entityId, $notes ?: $action, ['after' => $after]);
    }
}
