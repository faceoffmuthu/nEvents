#!/usr/bin/env php
<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

use NEvents\Core\Application;
use NEvents\Repositories\UserRepository;
use Ramsey\Uuid\Uuid;

$app = Application::getInstance();
$app->bootstrap(BASE_PATH);

/** @var UserRepository $users */
$users = $app->get(UserRepository::class);

echo "Create Super Administrator Account\n";
echo "===================================\n\n";

$name     = readline("Full Name:  ");
$email    = readline("Email:      ");
$password = readline("Password:   ");

if (!$name || !$email || !$password) {
    echo "All fields are required.\n";
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Invalid email address.\n";
    exit(1);
}

if (strlen($password) < 8) {
    echo "Password must be at least 8 characters.\n";
    exit(1);
}

$email = strtolower(trim($email));

if ($users->findByEmail($email)) {
    echo "A user with that email already exists.\n";
    exit(1);
}

$algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;

$userId = $users->create([
    'uuid'          => Uuid::uuid4()->toString(),
    'name'          => trim($name),
    'email'         => $email,
    'password_hash' => password_hash($password, $algorithm),
]);

// Mark the account active and pre-verified so it can log in immediately.
$users->update($userId, [
    'status'            => 'active',
    'email_verified_at' => date('Y-m-d H:i:s'),
    'onboarding_done'   => 1,
]);

$users->createProfile($userId);
$users->createNotificationPreferences($userId);

// Assign super_admin role (id=1, seeded in database/seeds/001_seed_base_data.sql)
$users->assignRole($userId, 1);

echo "\nSuper admin created!\n";
echo "   Name:  {$name}\n";
echo "   Email: {$email}\n";
echo "   Login at: " . rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/') . "/login\n\n";
