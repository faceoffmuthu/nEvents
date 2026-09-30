<?php

declare(strict_types=1);

namespace NEvents\Core;

use Monolog\Logger;

/**
 * Global error/exception handler.
 *
 * Registered as early as possible in public/index.php (before app bootstrap,
 * so it also covers failures during bootstrap itself) with a safe debug=false
 * default, then upgraded via setDebug() once config.app.debug is known.
 *
 * - Uncaught exceptions and true fatal errors render a branded error page
 *   (or a JSON envelope for API/AJAX requests) instead of a raw PHP dump,
 *   and are logged to storage/logs/app.log via Monolog.
 * - Regular PHP warnings/notices are logged (respecting error_reporting()
 *   and @-suppression) but not thrown, so existing control flow is unchanged.
 */
final class ErrorHandler
{
    private static bool $debug = false;

    public static function register(string $basePath, bool $debug): void
    {
        self::$debug = $debug;
        Log::init($basePath);

        error_reporting(E_ALL);
        ini_set('display_errors', '0');

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function setDebug(bool $debug): void
    {
        self::$debug = $debug;
    }

    public static function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if (!(error_reporting() & $severity)) {
            return false; // respects error_reporting() config and @-suppression
        }
        self::logger()->warning($message, ['file' => $file, 'line' => $line, 'severity' => $severity]);
        return true; // mark handled so PHP doesn't also print it
    }

    public static function handleException(\Throwable $e): void
    {
        self::logger()->error($e->getMessage(), ['exception' => $e]);
        self::respond($e);
    }

    public static function handleShutdown(): void
    {
        $err = error_get_last();
        if ($err === null || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        self::logger()->critical($err['message'], $err);
        self::respond(new \ErrorException($err['message'], 0, $err['type'], $err['file'], $err['line']));
    }

    private static function respond(\Throwable $e): void
    {
        if (headers_sent()) {
            return; // response already streaming; nothing more we can safely send
        }

        http_response_code(500);

        if (self::wantsJson()) {
            header('Content-Type: application/json');
            echo json_encode(self::$debug
                ? [
                    'success'   => false,
                    'message'   => $e->getMessage(),
                    'exception' => $e::class,
                    'file'      => $e->getFile(),
                    'line'      => $e->getLine(),
                ]
                : ['success' => false, 'message' => 'Something went wrong. Please try again.'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            exit(1);
        }

        header('Content-Type: text/html; charset=UTF-8');
        echo self::renderHtml($e);
        exit(1);
    }

    private static function renderHtml(\Throwable $e): string
    {
        // Prefer the app's own styled error view; fall back to inline HTML
        // if the view layer itself isn't safely usable (e.g. a failure during
        // app bootstrap, before session/config are ready).
        try {
            $view = new View();
            return $view->render('errors.500', [
                'title'     => 'Something Went Wrong — N Events',
                'debug'     => self::$debug,
                'exception' => $e,
            ]);
        } catch (\Throwable) {
            // fall through to the static fallback below
        }

        $detail = self::$debug
            ? '<pre style="text-align:left;white-space:pre-wrap;word-break:break-word;background:#0d1120;color:#94a3b8;padding:1rem;border-radius:8px;max-width:800px;margin:1.5rem auto 0;font-size:.8rem">'
                . htmlspecialchars($e::class . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString(), ENT_QUOTES, 'UTF-8')
              . '</pre>'
            : '';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Something Went Wrong</title></head>'
            . '<body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#080b14;color:#e2e8f0;font-family:system-ui,sans-serif;text-align:center;padding:2rem">'
            . '<div><div style="font-size:4rem;margin-bottom:1rem">⚠️</div>'
            . '<h1 style="font-size:1.5rem;margin-bottom:.5rem">Something went wrong</h1>'
            . '<p style="color:#94a3b8">We hit an unexpected error. Please try again in a moment.</p>'
            . $detail
            . '<a href="/" style="display:inline-block;margin-top:1.5rem;color:#6c63ff">Back to Home</a></div></body></html>';
    }

    private static function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xrw    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $path   = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
        return $xrw === 'XMLHttpRequest' || str_contains($accept, 'application/json') || str_starts_with($path, '/api/');
    }

    private static function logger(): Logger
    {
        return Log::get();
    }
}
