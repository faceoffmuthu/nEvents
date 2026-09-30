<?php

declare(strict_types=1);

namespace NEvents\Middleware;

use NEvents\Core\Request;
use NEvents\Core\Response;

class AuthMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!$request->isLoggedIn()) {
            if ($request->expectsJson()) {
                return Response::json(['error' => 'Unauthenticated.'], 401);
            }
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '/';
            return Response::redirect(\NEvents\Core\View::url('login'));
        }
        return $next($request);
    }
}
