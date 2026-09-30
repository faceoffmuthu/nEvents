<?php

declare(strict_types=1);

namespace NEvents\Repositories;

use NEvents\Core\Database\Connection;

class CategoryRepository
{
    public function __construct(private Connection $db) {}

    public function all(bool $activeOnly = true): array
    {
        $where = $activeOnly ? "WHERE status = 'active'" : '';
        return $this->db->select("SELECT * FROM categories {$where} ORDER BY parent_id ASC, sort_order ASC");
    }

    public function topLevel(): array
    {
        return $this->db->select("SELECT * FROM categories WHERE parent_id IS NULL AND status = 'active' ORDER BY sort_order ASC");
    }

    public function withChildren(): array
    {
        $all = $this->all();
        $tree = [];
        $map  = [];
        foreach ($all as $cat) {
            $map[$cat['id']] = $cat;
            $map[$cat['id']]['children'] = [];
        }
        foreach ($map as &$cat) {
            if ($cat['parent_id']) {
                $map[$cat['parent_id']]['children'][] = &$cat;
            } else {
                $tree[] = &$cat;
            }
        }
        return $tree;
    }

    public function findBySlug(string $slug): array|false
    {
        return $this->db->selectOne("SELECT * FROM categories WHERE slug = :slug", [':slug' => $slug]);
    }

    public function getSubcategories(int $parentId): array
    {
        return $this->db->select("SELECT * FROM categories WHERE parent_id = :pid AND status = 'active' ORDER BY sort_order ASC", [':pid' => $parentId]);
    }

    public function getFeatured(int $limit = 8): array
    {
        return $this->db->select("SELECT * FROM categories WHERE parent_id IS NULL AND status = 'active' ORDER BY sort_order ASC LIMIT :limit", [':limit' => $limit]);
    }
}
