#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Creates (or resets) the demo account for testing the platform locally:
 *   demo@nevents.local / DemoPass123 — a regular, verified, active user.
 * Safe to run again: it resets the password and clears the account's failed logins.
 * Refuses to run when APP_ENV=production.
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

use NEvents\Core\Application;
use NEvents\Core\Database\Connection;
use NEvents\Repositories\UserRepository;
use Ramsey\Uuid\Uuid;

$app = Application::getInstance();
$app->bootstrap(BASE_PATH);

if (($_ENV['APP_ENV'] ?? '') === 'production') {
    echo "Refusing to create a demo account with a published password in production.\n";
    exit(1);
}

const DEMO_EMAIL    = 'demo@nevents.local';
const DEMO_PASSWORD = 'DemoPass123';

/** @var UserRepository $users */
$users = $app->get(UserRepository::class);
/** @var Connection $db */
$db    = $app->get(Connection::class);

$hash = password_hash(DEMO_PASSWORD, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
$user = $users->findByEmail(DEMO_EMAIL);

if ($user) {
    $userId = (int) $user['id'];
    $users->update($userId, ['password_hash' => $hash]);
} else {
    $userId = $users->create([
        'uuid'          => Uuid::uuid4()->toString(),
        'name'          => 'Demo User',
        'email'         => DEMO_EMAIL,
        'password_hash' => $hash,
    ]);
    $users->createProfile($userId);
    $users->createNotificationPreferences($userId);
}

$users->update($userId, [
    'status'            => 'active',
    'email_verified_at' => $user['email_verified_at'] ?? date('Y-m-d H:i:s'),
    'onboarding_done'   => 1,
]);
$users->assignRole($userId, 5);   // 'user' (database/seeds/001_seed_base_data.sql)
$db->delete('DELETE FROM login_attempts WHERE identifier = :e AND success = 0', [':e' => DEMO_EMAIL]);

echo ($user ? "Demo account reset." : "Demo account created.") . "\n";
echo "   Email:    " . DEMO_EMAIL . "\n";
echo "   Password: " . DEMO_PASSWORD . "\n";
echo "   Sign in:  " . rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/') . "/login\n";
