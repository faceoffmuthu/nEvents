#!/usr/bin/env php
<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

use NEvents\Core\Application;
use NEvents\Core\Database\Connection;

$app = Application::getInstance();
$app->bootstrap(BASE_PATH);
$db  = $app->get(Connection::class);

$maxJobs    = (int) ($_ENV['WORKER_MAX_JOBS']    ?? 100);
$sleepMs    = (int) ($_ENV['WORKER_SLEEP_MS']    ?? 1000);
$maxRetries = (int) ($_ENV['WORKER_MAX_RETRIES'] ?? 3);
$pid        = (string) getmypid();

echo "[worker] Starting. PID={$pid}\n";

$processed = 0;
while ($processed < $maxJobs) {
    // Claim one pending, unlocked, due job atomically. The jobs table has no
    // 'status' column — pending/processing/completed/failed states are all
    // inferred from locked_at/completed_at/failed_at being NULL or not.
    $db->statement(
        "UPDATE jobs
            SET locked_at = NOW(),
                locked_by = :pid,
                attempts  = attempts + 1
          WHERE completed_at IS NULL
            AND failed_at IS NULL
            AND locked_at IS NULL
            AND available_at <= NOW()
            AND id = (
              SELECT id FROM (
                SELECT id FROM jobs
                 WHERE completed_at IS NULL AND failed_at IS NULL AND locked_at IS NULL
                   AND available_at <= NOW()
                 ORDER BY priority DESC, id ASC LIMIT 1
              ) AS tmp
            )",
        [':pid' => $pid]
    );

    $job = $db->selectOne(
        "SELECT * FROM jobs WHERE locked_by = :pid AND completed_at IS NULL AND failed_at IS NULL ORDER BY id ASC LIMIT 1",
        [':pid' => $pid]
    );

    if (!$job) {
        usleep($sleepMs * 1000);
        continue;
    }

    echo "[worker] Running job #{$job['id']} type={$job['job_type']}\n";

    try {
        $payload = json_decode($job['payload_json'] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
        runJob($db, $job, $payload, $app);

        $db->update(
            "UPDATE jobs SET completed_at = :now, locked_at = NULL, locked_by = NULL WHERE id = :id",
            [':now' => date('Y-m-d H:i:s'), ':id' => $job['id']]
        );

        echo "[worker] Job #{$job['id']} completed.\n";
        $processed++;

    } catch (Throwable $e) {
        echo "[worker] Job #{$job['id']} FAILED: " . $e->getMessage() . "\n";

        if ((int) $job['attempts'] >= $maxRetries) {
            $db->update(
                "UPDATE jobs SET failed_at = :now, error_message = :err, locked_at = NULL, locked_by = NULL WHERE id = :id",
                [':now' => date('Y-m-d H:i:s'), ':err' => $e->getMessage(), ':id' => $job['id']]
            );

            $db->insert(
                "INSERT INTO failed_jobs (job_type, payload_json, error_message, failed_at) VALUES (:type, :payload, :err, :now)",
                [
                    ':type'    => $job['job_type'],
                    ':payload' => $job['payload_json'],
                    ':err'     => $e->getMessage(),
                    ':now'     => date('Y-m-d H:i:s'),
                ]
            );
        } else {
            $backoff = min(3600, 30 * (2 ** max(0, (int) $job['attempts'] - 1)));
            $db->update(
                "UPDATE jobs SET locked_at = NULL, locked_by = NULL, available_at = :available, error_message = :err WHERE id = :id",
                [
                    ':available' => date('Y-m-d H:i:s', time() + $backoff),
                    ':err'       => $e->getMessage(),
                    ':id'        => $job['id'],
                ]
            );
        }
    }
}

echo "[worker] Done. Processed {$processed} jobs.\n";

function runJob(Connection $db, array $job, array $payload, Application $app): void
{
    match ($job['job_type']) {
        'send_notification', 'send_email' => runSendNotification($db, $payload),
        'ingest_source'      => runIngestSource($db, $payload, $app),
        'verify_event'       => runVerifyEvent($db, $payload),
        default              => throw new RuntimeException("Unknown job type: {$job['job_type']}"),
    };
}

/** Delivers queued notification emails (email is the only channel). */
function runSendNotification(Connection $db, array $payload): void
{
    $stats = Application::getInstance()->get(\NEvents\Services\Notifications\NotificationService::class)
        ->deliverDue((int) ($payload['limit'] ?? 50));
    echo '  [notify] ' . json_encode($stats) . "\n";
}

function runIngestSource(Connection $db, array $payload, Application $app): void
{
    $ingestion = $app->get(\NEvents\Services\Ingestion\IngestionService::class);
    $sourceId  = (int) ($payload['source_id'] ?? 0);
    $fetch     = $ingestion->runSource($sourceId);
    $process   = $ingestion->processPendingRecords($sourceId, 200);
    echo "  [ingest] Source #{$sourceId} " . json_encode(['fetch' => $fetch, 'process' => $process]) . "\n";
}

function runVerifyEvent(Connection $db, array $payload): void
{
    // Stub — real implementation in VerificationService
    $eventId = (int) ($payload['event_id'] ?? 0);
    echo "  [verify] Event #{$eventId}\n";

    $db->update(
        "UPDATE events SET trust_score = :score, last_verified_at = :now WHERE id = :id",
        [':score' => 50, ':now' => date('Y-m-d H:i:s'), ':id' => $eventId]
    );
}
