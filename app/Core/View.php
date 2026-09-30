<?php

declare(strict_types=1);

namespace NEvents\Core;

class View
{
    private string $viewsPath;

    public function __construct()
    {
        $this->viewsPath = dirname(__DIR__, 2) . '/resources/views';
    }

    public function render(string $template, array $data = []): string
    {
        $file = $this->viewsPath . '/' . str_replace('.', '/', $template) . '.php';

        if (!file_exists($file)) {
            throw new \RuntimeException("View [{$template}] not found at [{$file}].");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return ob_get_clean();
    }

    public function makeResponse(string $template, array $data = [], int $status = 200): Response
    {
        $body = $this->render($template, $data);
        return Response::html($body, $status);
    }

    /**
     * Static factory for controllers that don't inject View as a dependency.
     * Templates use '/' or '.' separators (e.g. 'admin/dashboard' or 'events.show').
     */
    public static function make(string $template, array $data = [], int $status = 200): Response
    {
        return (new self())->makeResponse($template, $data, $status);
    }

    /**
     * Renders resources/views/partials/{$name}.php in its own scope (no variable
     * leakage into or out of the calling template) and returns the HTML.
     */
    public static function partial(string $name, array $vars = []): string
    {
        return (new self())->render('partials.' . $name, $vars);
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function asset(string $path): string
    {
        $base = rtrim($_ENV['APP_URL'] ?? '', '/');
        return $base . '/' . ltrim($path, '/');
    }

    public static function url(string $path = ''): string
    {
        $base = rtrim($_ENV['APP_URL'] ?? '', '/');
        return $base . '/' . ltrim($path, '/');
    }

    public static function csrf(): string
    {
        $token = $_SESSION['csrf_token'] ?? '';
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function csrfToken(): string
    {
        return $_SESSION['csrf_token'] ?? '';
    }
}
