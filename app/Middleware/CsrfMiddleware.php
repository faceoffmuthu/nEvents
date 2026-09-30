<?php

declare(strict_types=1);

namespace NEvents\Middleware;

use NEvents\Core\Request;
use NEvents\Core\Response;

class CsrfMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        if (in_array($request->getMethod(), ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            $token = $request->post('_csrf') ?? $request->header('X-CSRF-Token') ?? '';
            if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
                if ($request->expectsJson()) {
                    return Response::json(['error' => 'CSRF token mismatch.'], 419);
                }
                return Response::html('<h1>419 — CSRF token mismatch.</h1>', 419);
            }
        }

        return $next($request);
    }
}
