<?php

declare(strict_types=1);

namespace NEvents\Controllers\Admin;

use NEvents\Core\Request;
use NEvents\Core\Response;
use NEvents\Core\View;
use NEvents\Core\Database\Connection;

class AdminUserController
{
    public function __construct(private Connection $db) {}

    public function index(Request $request): Response
    {
        $page   = max(1, (int) $request->query('page', 1));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $users = $this->db->select(
            "SELECT u.id, u.name, u.email, u.status, u.email_verified_at, u.created_at,
                    GROUP_CONCAT(r.name ORDER BY r.id SEPARATOR ', ') AS roles
               FROM users u
               LEFT JOIN user_roles ur ON ur.user_id = u.id
               LEFT JOIN roles r ON r.id = ur.role_id
              GROUP BY u.id
              ORDER BY u.created_at DESC
              LIMIT {$limit} OFFSET {$offset}"
        );

        $total = (int) ($this->db->selectOne("SELECT COUNT(*) AS n FROM users")['n'] ?? 0);

        return View::make('admin/users/index', [
            'title' => 'Manage Users',
            'users' => $users,
            'total' => $total,
            'page'  => $page,
        ]);
    }

    public function show(Request $request): Response
    {
        $id   = (int) $request->param('id', 0);
        $user = $this->db->selectOne("SELECT * FROM users WHERE id = :id", [':id' => $id]);
        if (!$user) return Response::redirect('/admin/users');
        return View::make('admin/users/index', ['title' => 'User: ' . ($user['name'] ?? ''), 'users' => [$user], 'total' => 1, 'page' => 1]);
    }

    public function suspend(Request $request): Response
    {
        $id = (int) $request->param('id', 0);
        $this->db->update('UPDATE users SET status = :status WHERE id = :id', [':status' => 'suspended', ':id' => $id]);
        $_SESSION['flash_success'] = 'User suspended.';
        return Response::redirect('/admin/users');
    }

    public function updateStatus(Request $request): Response
    {
        $id     = (int) $request->param('id', 0);
        $status = $request->post('status', '');
        $valid  = ['active', 'inactive', 'suspended', 'deleted'];

        if ($id > 0 && in_array($status, $valid, true)) {
            $this->db->update('UPDATE users SET status = :status WHERE id = :id', [':status' => $status, ':id' => $id]);
            $_SESSION['flash_success'] = "User status updated.";
        }

        return Response::redirect('/admin/users');
    }
}
