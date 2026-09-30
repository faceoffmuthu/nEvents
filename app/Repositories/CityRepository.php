<?php

declare(strict_types=1);

namespace NEvents\Repositories;

use NEvents\Core\Database\Connection;

class CityRepository
{
    public function __construct(private Connection $db) {}

    public function all(): array
    {
        return $this->db->select("SELECT c.*, d.name AS district_name FROM cities c JOIN districts d ON d.id = c.district_id WHERE c.is_active = 1 ORDER BY c.sort_order ASC, c.name ASC");
    }

    public function featured(): array
    {
        return $this->db->select("SELECT * FROM cities WHERE is_active = 1 AND is_featured = 1 ORDER BY sort_order ASC");
    }

    public function findBySlug(string $slug): array|false
    {
        return $this->db->selectOne(
            "SELECT c.*, s.name AS state_name FROM cities c JOIN districts d ON d.id = c.district_id JOIN states s ON s.id = d.state_id
              WHERE c.slug = :slug AND c.is_active = 1",
            [':slug' => $slug]
        );
    }

    public function findById(int $id): array|false
    {
        return $this->db->selectOne("SELECT * FROM cities WHERE id = :id", [':id' => $id]);
    }

    public function searchByName(string $term): array
    {
        return $this->db->select("SELECT id, name, slug FROM cities WHERE name LIKE :term AND is_active = 1 ORDER BY sort_order ASC LIMIT 10", [':term' => $term . '%']);
    }
}
