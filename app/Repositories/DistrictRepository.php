<?php

declare(strict_types=1);

namespace NEvents\Repositories;

use NEvents\Core\Database\Connection;

/**
 * Single source of truth for India's states / union territories and their
 * districts. Every selector (homepage, dashboard, onboarding, search, admin)
 * reads from here — never a hardcoded/duplicated list.
 */
class DistrictRepository
{
    public function __construct(private Connection $db) {}

    public function all(): array
    {
        return $this->db->select(
            "SELECT * FROM districts WHERE is_active = 1 ORDER BY name ASC"
        );
    }

    /** Active states and union territories, alphabetical. */
    public function states(): array
    {
        return $this->db->select("SELECT id, name, slug FROM states WHERE is_active = 1 ORDER BY name ASC");
    }

    /** Active districts of one state, alphabetical. */
    public function byState(int $stateId): array
    {
        return $this->db->select(
            "SELECT * FROM districts WHERE state_id = :s AND is_active = 1 ORDER BY name ASC",
            [':s' => $stateId]
        );
    }

    /** Every active district with its state (for pickers and search). */
    public function allWithState(): array
    {
        return $this->db->select(
            "SELECT d.id, d.name, d.slug, d.state_id, s.name AS state_name
               FROM districts d JOIN states s ON s.id = d.state_id
              WHERE d.is_active = 1 AND s.is_active = 1
              ORDER BY s.name ASC, d.name ASC"
        );
    }

    /** Districts that have any events, labelled with their state (admin filters). */
    public function withEvents(): array
    {
        return $this->db->select(
            "SELECT d.id, CONCAT(d.name, ', ', s.name) AS name
               FROM districts d JOIN states s ON s.id = d.state_id
              WHERE EXISTS (SELECT 1 FROM cities c JOIN events e ON e.city_id = c.id WHERE c.district_id = d.id)
              ORDER BY s.name ASC, d.name ASC"
        );
    }

    public function findById(int $id): array|false
    {
        return $this->db->selectOne(
            "SELECT d.*, s.name AS state_name FROM districts d JOIN states s ON s.id = d.state_id WHERE d.id = :id",
            [':id' => $id]
        );
    }

    public function findActiveById(int $id): array|false
    {
        return $this->db->selectOne(
            "SELECT d.*, s.name AS state_name FROM districts d JOIN states s ON s.id = d.state_id WHERE d.id = :id AND d.is_active = 1",
            [':id' => $id]
        );
    }

    public function findBySlug(string $slug): array|false
    {
        return $this->db->selectOne(
            "SELECT d.*, s.name AS state_name FROM districts d JOIN states s ON s.id = d.state_id WHERE d.slug = :slug AND d.is_active = 1",
            [':slug' => $slug]
        );
    }

    /** Normalized-name lookup (lowercase, trimmed) used during location normalization. */
    public function findByNormalizedName(string $normalized): array|false
    {
        return $this->db->selectOne(
            "SELECT * FROM districts WHERE LOWER(name) = :n AND is_active = 1",
            [':n' => $normalized]
        );
    }

    /** Alias -> district resolution (e.g. 'trichy' -> Tiruchirappalli). */
    public function findByAlias(string $normalizedAlias): array|false
    {
        return $this->db->selectOne(
            "SELECT d.* FROM district_aliases da
               JOIN districts d ON d.id = da.district_id
              WHERE da.alias_norm = :a AND d.is_active = 1",
            [':a' => $normalizedAlias]
        );
    }

    /** All city IDs belonging to a district — used to filter events by district. */
    public function cityIdsInDistrict(int $districtId): array
    {
        $rows = $this->db->select(
            "SELECT id FROM cities WHERE district_id = :did AND is_active = 1",
            [':did' => $districtId]
        );
        return array_map(fn($r) => (int) $r['id'], $rows);
    }

    public function countEvents(int $districtId): int
    {
        $row = $this->db->selectOne(
            "SELECT COUNT(*) AS n
               FROM events e
               JOIN cities c ON c.id = e.city_id
              WHERE c.district_id = :did AND e.status = 'published'",
            [':did' => $districtId]
        );
        return (int) ($row['n'] ?? 0);
    }

    public function setActive(int $districtId, bool $active): void
    {
        $this->db->update(
            "UPDATE districts SET is_active = :active WHERE id = :id",
            [':active' => $active ? 1 : 0, ':id' => $districtId]
        );
    }
}
