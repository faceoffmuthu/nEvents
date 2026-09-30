#!/usr/bin/env php
<?php

/**
 * N Events background scheduler. Run it every 5 minutes from cron /
 * Windows Task Scheduler:
 *
 *   php bin/scheduler.php                 run every task that is due
 *   php bin/scheduler.php <task> [...]    run specific task(s) now
 *   php bin/scheduler.php --list          list tasks and when they last ran
 *   php bin/scheduler.php --log           also append output to storage/logs/scheduler-YYYY-MM-DD.log
 *                                         (used by the Windows scheduled task, which has no console)
 *
 * Nothing here runs during a web request — pages only read MySQL.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

use NEvents\Core\Application;
use NEvents\Core\Database\Connection;
use NEvents\Core\Log;
use NEvents\Services\Events\EventLifecycleService;
use NEvents\Services\Ingestion\IngestionService;
use NEvents\Services\Notifications\NotificationService;

$app = Application::getInstance();
$app->bootstrap(BASE_PATH);
$db = $app->get(Connection::class);

/** name => [interval minutes, description, callable] */
$tasks = [
    'discover-web' => [5, 'Run due web/feed/sitemap/search sources (per-source refresh_interval)',
        fn() => runDueSources($db, $app, social: false)],
    'discover-social' => [5, 'Run due Instagram/Facebook/X sources (only if enabled + configured)',
        fn() => runDueSources($db, $app, social: true)],
    'process-candidates' => [5, 'Turn pending raw candidates into canonical events / review items',
        fn() => $app->get(IngestionService::class)->processPendingRecords(null, 200)],
    'refresh-sources' => [60, 'Track events no longer seen at their source (stale -> missing -> outdated)',
        fn() => $app->get(EventLifecycleService::class)->refreshSourceFreshness()],
    'expire-events' => [30, 'Mark events whose last occurrence ended as completed',
        fn() => ['completed' => $app->get(EventLifecycleService::class)->expirePastEvents()]],
    'queue-daily-digest' => [15, 'Queue daily digest emails (respects each user\'s digest time)',
        fn() => ['queued' => $app->get(NotificationService::class)->queueDigests('daily')]],
    'queue-weekly-digest' => [15, 'Queue weekly digest emails (digest day, default Monday)',
        fn() => ['queued' => $app->get(NotificationService::class)->queueDigests('weekly')]],
    'queue-reminders' => [10, 'Queue saved-event reminders (tomorrow, starting soon, registration closing)',
        fn() => ['queued' => $app->get(NotificationService::class)->queueSavedEventReminders()]],
    'send-emails' => [1, 'Deliver queued notification emails via SMTP',
        fn() => $app->get(NotificationService::class)->deliverDue(100)],
    'purge-social-raw' => [1440, 'Drop stored social post text after the retention window',
        fn() => ['purged' => $app->get(EventLifecycleService::class)->purgeSocialPayloads()]],
];

$args = array_slice($argv, 1);
$logToFile = in_array('--log', $args, true);
$args = array_values(array_diff($args, ['--log']));

function out(string $line): void
{
    global $logToFile;
    echo $line;
    if ($logToFile) {
        @file_put_contents(BASE_PATH . '/storage/logs/scheduler-' . date('Y-m-d') . '.log', '[' . date('H:i:s') . '] ' . $line, FILE_APPEND | LOCK_EX);
    }
}

if (in_array('--list', $args, true) || in_array('-h', $args, true) || in_array('--help', $args, true)) {
    echo "Tasks:\n";
    foreach ($tasks as $name => [$every, $desc]) {
        printf("  %-20s every %4d min  last: %-19s  %s\n", $name, $every, lastRun($db, $name) ?? 'never', $desc);
    }
    exit(0);
}

$selected = $args ?: array_keys($tasks);
$unknown  = array_diff($selected, array_keys($tasks));
if ($unknown) {
    fwrite(STDERR, 'Unknown task(s): ' . implode(', ', $unknown) . " — see --list\n");
    exit(1);
}

// One scheduler at a time
$lock = fopen(BASE_PATH . '/storage/scheduler.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    out("[scheduler] Another run is in progress — exiting.\n");
    exit(0);
}

$exit = 0;
foreach ($selected as $name) {
    [$every, , $fn] = $tasks[$name];
    $last = lastRun($db, $name);
    if (!$args && $last !== null && strtotime($last) > time() - $every * 60 + 30) {
        continue; // not due
    }
    $t = microtime(true);
    try {
        $result = $fn();
        markRun($db, $name);
        out(sprintf("[scheduler] %-20s ok  %5.1fs  %s\n", $name, microtime(true) - $t, json_encode($result)));
    } catch (Throwable $e) {
        $exit = 1;
        out(sprintf("[scheduler] %-20s FAILED: %s\n", $name, $e->getMessage()));
        Log::get()->error('scheduler_task_failed', ['task' => $name, 'error' => $e->getMessage()]);
    }
}

flock($lock, LOCK_UN);
exit($exit);

// ---------------------------------------------------------------------

function runDueSources(Connection $db, Application $app, bool $social): array
{
    $platformSql = $social ? "platform IN ('instagram','facebook','x')" : "platform NOT IN ('instagram','facebook','x')";
    $sources = $db->select(
        "SELECT id, name FROM sources
          WHERE enabled = 1 AND adapter_class IS NOT NULL AND {$platformSql}
            AND (last_checked_at IS NULL OR last_checked_at < DATE_SUB(NOW(), INTERVAL refresh_interval MINUTE))
          ORDER BY last_checked_at IS NULL DESC, last_checked_at ASC"
    );
    $ingestion = $app->get(IngestionService::class);
    $out = [];
    foreach ($sources as $s) {
        try {
            $out[$s['name']] = $ingestion->runSource((int) $s['id']);
        } catch (Throwable $e) {
            $out[$s['name']] = 'failed: ' . $e->getMessage();
        }
    }
    return $out ?: ['sources_due' => 0];
}

function lastRun(Connection $db, string $task): ?string
{
    $row = $db->selectOne("SELECT value FROM system_settings WHERE key_name = :k", [':k' => 'scheduler_last_' . $task]);
    return $row['value'] ?? null;
}

function markRun(Connection $db, string $task): void
{
    $db->statement(
        "INSERT INTO system_settings (key_name, value, type, group_name) VALUES (:k, :v, 'string', 'scheduler')
         ON DUPLICATE KEY UPDATE value = VALUES(value)",
        [':k' => 'scheduler_last_' . $task, ':v' => date('Y-m-d H:i:s')]
    );
}
