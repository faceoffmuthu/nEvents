<?php

declare(strict_types=1);

namespace NEvents\Middleware;

use NEvents\Core\Request;
use NEvents\Core\Response;

class AdminMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!$request->isLoggedIn()) {
            return Response::redirect(\NEvents\Core\View::url('admin/login'));
        }

        $roles = $_SESSION['user_roles'] ?? [];
        $allowed = ['admin', 'super_admin', 'moderator'];

        if (empty(array_intersect($roles, $allowed))) {
            return Response::html('<h1>403 — Access Denied</h1>', 403);
        }

        return $next($request);
    }
}
