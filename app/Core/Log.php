<?php

declare(strict_types=1);

namespace NEvents\Core;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Level;

/**
 * Shared application logger (storage/logs/app-YYYY-MM-DD.log, rotated daily).
 * Used by ErrorHandler and any service that needs to log without depending
 * on the DI container (e.g. MailService, called from contexts where a fresh
 * exception during construction would itself need to be logged).
 */
final class Log
{
    private static ?Logger $logger = null;
    private static string $basePath = '';

    public static function init(string $basePath): void
    {
        self::$basePath = $basePath;
    }

    public static function get(): Logger
    {
        if (self::$logger === null) {
            $basePath = self::$basePath !== '' ? self::$basePath : dirname(__DIR__, 2);
            $logDir   = $basePath . '/storage/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0775, true);
            }
            self::$logger = new Logger('nevents');
            self::$logger->pushHandler(new RotatingFileHandler($logDir . '/app.log', 14, Level::Debug));
        }
        return self::$logger;
    }
}
