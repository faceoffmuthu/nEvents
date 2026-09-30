#!/usr/bin/env php
<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

$host    = $_ENV['DB_HOST']     ?? '127.0.0.1';
$port    = $_ENV['DB_PORT']     ?? '3306';
$db      = $_ENV['DB_DATABASE'] ?? 'nevents';
$user    = $_ENV['DB_USERNAME'] ?? 'root';
$pass    = $_ENV['DB_PASSWORD'] ?? '';

try {
    // Create database if it doesn't exist
    $pdo = new PDO("mysql:host={$host};port={$port}", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database '{$db}' ready.\n";

    // Connect to the database
    $pdo->exec("USE `{$db}`");
    $pdo->exec("SET NAMES utf8mb4");

    // Run migrations
    $migrationDir = BASE_PATH . '/database/migrations';
    $files = glob($migrationDir . '/*.sql');
    sort($files);

    foreach ($files as $file) {
        echo "Running migration: " . basename($file) . "…\n";
        $sql = file_get_contents($file);

        // Split on semicolons while ignoring inside strings (basic approach)
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($s) => strlen($s) > 5
        );

        foreach ($statements as $stmt) {
            if (trim($stmt)) {
                $pdo->exec($stmt);
            }
        }
        echo "  ✓ Done.\n";
    }

    // Run seeds
    $seedDir = BASE_PATH . '/database/seeds';
    $seeds   = glob($seedDir . '/*.sql');
    sort($seeds);

    foreach ($seeds as $file) {
        echo "Running seed: " . basename($file) . "…\n";
        $sql = file_get_contents($file);
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($s) => strlen($s) > 5
        );
        foreach ($statements as $stmt) {
            if (trim($stmt)) {
                try {
                    $pdo->exec($stmt);
                } catch (PDOException $e) {
                    // Ignore duplicate-key on seeds (INSERT IGNORE)
                    if ($e->getCode() !== '23000') throw $e;
                }
            }
        }
        echo "  ✓ Done.\n";
    }

    echo "\n✅ Migration and seeding complete!\n";
    echo "\nNext steps:\n";
    echo "  1. Create a super-admin account: php bin/create-admin.php\n";
    echo "  2. Copy .env.example to .env and configure\n";
    echo "  3. Run: php -S localhost:8080 -t public\n\n";

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
}
