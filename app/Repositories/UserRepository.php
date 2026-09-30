<?php

declare(strict_types=1);

namespace NEvents\Repositories;

use NEvents\Core\Database\Connection;

class UserRepository
{
    public function __construct(private Connection $db) {}

    public function findById(int $id): array|false
    {
        return $this->db->selectOne('SELECT * FROM users WHERE id = :id', [':id' => $id]);
    }

    public function findByEmail(string $email): array|false
    {
        return $this->db->selectOne('SELECT * FROM users WHERE email = :email', [':email' => $email]);
    }

    public function findByUuid(string $uuid): array|false
    {
        return $this->db->selectOne('SELECT * FROM users WHERE uuid = :uuid', [':uuid' => $uuid]);
    }

    public function create(array $data): int
    {
        return $this->db->insert("
            INSERT INTO users (uuid, name, email, password_hash, phone, whatsapp_number, status)
            VALUES (:uuid, :name, :email, :password_hash, :phone, :whatsapp, 'active')
        ", [
            ':uuid'          => $data['uuid'],
            ':name'          => $data['name'],
            ':email'         => $data['email'],
            ':password_hash' => $data['password_hash'],
            ':phone'         => $data['phone'] ?? null,
            ':whatsapp'      => $data['whatsapp_number'] ?? null,
        ]);
    }

    public function update(int $id, array $data): void
    {
        $sets   = [];
        $params = [':id' => $id];

        $allowed = ['name', 'phone', 'whatsapp_number', 'status', 'onboarding_step', 'onboarding_done', 'locale', 'last_login_at', 'last_login_ip', 'email_verified_at', 'password_hash'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[]            = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($sets)) return;
        $this->db->update('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
    }

    public function createProfile(int $userId): void
    {
        $this->db->statement('INSERT IGNORE INTO user_profiles (user_id) VALUES (:id)', [':id' => $userId]);
    }

    public function createNotificationPreferences(int $userId): void
    {
        $this->db->statement('INSERT IGNORE INTO notification_preferences (user_id) VALUES (:id)', [':id' => $userId]);
    }

    public function assignRole(int $userId, int $roleId, ?int $grantedBy = null): void
    {
        $this->db->statement(
            'INSERT IGNORE INTO user_roles (user_id, role_id, granted_by) VALUES (:u, :r, :g)',
            [':u' => $userId, ':r' => $roleId, ':g' => $grantedBy]
        );
    }

    public function getRoles(int $userId): array
    {
        return $this->db->select("
            SELECT r.name, r.slug
            FROM roles r
            JOIN user_roles ur ON ur.role_id = r.id
            WHERE ur.user_id = :uid
        ", [':uid' => $userId]);
    }

    public function getUserInterests(int $userId): array
    {
        return $this->db->select("
            SELECT c.id, c.name, c.slug, c.icon, c.color, c.parent_id, ui.weight
            FROM user_interests ui
            JOIN categories c ON c.id = ui.category_id
            WHERE ui.user_id = :uid
            ORDER BY c.sort_order ASC
        ", [':uid' => $userId]);
    }

    public function setInterests(int $userId, array $categoryIds): void
    {
        $this->db->delete('DELETE FROM user_interests WHERE user_id = :uid', [':uid' => $userId]);
        foreach ($categoryIds as $catId) {
            $this->db->statement(
                'INSERT IGNORE INTO user_interests (user_id, category_id) VALUES (:u, :c)',
                [':u' => $userId, ':c' => (int)$catId]
            );
        }
    }

    public function getPrimaryLocation(int $userId): array|false
    {
        return $this->db->selectOne("
            SELECT ul.*, c.name AS city_name, c.slug AS city_slug,
                   d.id AS resolved_district_id, d.name AS district_name, d.slug AS district_slug
            FROM user_locations ul
            LEFT JOIN cities c ON c.id = ul.city_id
            LEFT JOIN districts d ON d.id = COALESCE(ul.district_id, c.district_id)
            WHERE ul.user_id = :uid AND ul.is_primary = 1
            LIMIT 1
        ", [':uid' => $userId]);
    }

    /**
     * Sets (or updates) the user's primary district preference. This is the
     * one place logged-in district selection is persisted — the dashboard,
     * onboarding and any future selector all call this same method.
     */
    public function setDistrictPreference(int $userId, int $districtId): void
    {
        $existing = $this->db->selectOne(
            'SELECT id FROM user_locations WHERE user_id = :uid AND is_primary = 1',
            [':uid' => $userId]
        );

        if ($existing) {
            $this->db->update(
                'UPDATE user_locations SET district_id = :did WHERE id = :id',
                [':did' => $districtId, ':id' => $existing['id']]
            );
            return;
        }

        $this->db->insert(
            'INSERT INTO user_locations (user_id, district_id, is_primary) VALUES (:uid, :did, 1)',
            [':uid' => $userId, ':did' => $districtId]
        );
    }

    /** "All India": the user no longer has a home district. */
    public function clearDistrictPreference(int $userId): void
    {
        $this->db->update(
            'UPDATE user_locations SET district_id = NULL, city_id = NULL, area_id = NULL WHERE user_id = :uid AND is_primary = 1',
            [':uid' => $userId]
        );
    }

    public function setLocation(int $userId, array $data): void
    {
        $this->db->delete('DELETE FROM user_locations WHERE user_id = :uid AND is_primary = 1', [':uid' => $userId]);
        $this->db->insert("
            INSERT INTO user_locations (user_id, city_id, district_id, area_id, radius_km, is_primary, latitude, longitude)
            VALUES (:uid, :city_id, :district_id, :area_id, :radius_km, 1, :lat, :lon)
        ", [
            ':uid'         => $userId,
            ':city_id'     => $data['city_id']     ?? null,
            ':district_id' => $data['district_id'] ?? null,
            ':area_id'     => $data['area_id']      ?? null,
            ':radius_km'   => $data['radius_km']    ?? 25,
            ':lat'         => $data['latitude']     ?? null,
            ':lon'         => $data['longitude']    ?? null,
        ]);
    }

    public function getNotificationPreferences(int $userId): array|false
    {
        return $this->db->selectOne('SELECT * FROM notification_preferences WHERE user_id = :uid', [':uid' => $userId]);
    }

    public function updateNotificationPreferences(int $userId, array $prefs): void
    {
        $this->db->update("
            UPDATE notification_preferences SET
                email_enabled    = :email,
                daily_digest     = :daily,
                weekly_digest    = :weekly,
                alert_high_match = :alert,
                reminder_24h     = :r24h,
                reminder_2h      = :r2h,
                event_updates    = :updates,
                digest_time      = :digest_time,
                min_score        = :min_score
            WHERE user_id = :uid
        ", [
            ':uid'       => $userId,
            ':email'     => (int)($prefs['email_enabled']    ?? 1),
            ':updates'   => (int)($prefs['event_updates']    ?? 1),
            ':digest_time' => $prefs['digest_time']          ?? '08:00:00',
            ':daily'     => (int)($prefs['daily_digest']     ?? 1),
            ':weekly'    => (int)($prefs['weekly_digest']    ?? 0),
            ':alert'     => (int)($prefs['alert_high_match'] ?? 1),
            ':r24h'      => (int)($prefs['reminder_24h']     ?? 1),
            ':r2h'       => (int)($prefs['reminder_2h']      ?? 0),
            ':min_score' => (int)($prefs['min_score']        ?? 40),
        ]);
    }

    public function recordConsent(int $userId, string $type, bool $granted, string $ip = '', string $ua = ''): void
    {
        $this->db->insert("
            INSERT INTO consents (user_id, consent_type, granted, ip_address, user_agent)
            VALUES (:uid, :type, :granted, :ip, :ua)
        ", [
            ':uid'     => $userId,
            ':type'    => $type,
            ':granted' => (int)$granted,
            ':ip'      => $ip,
            ':ua'      => substr($ua, 0, 500),
        ]);
    }

    public function createAuthToken(int $userId, string $type, string $tokenHash, \DateTimeImmutable $expiresAt): void
    {
        $this->db->insert("
            INSERT INTO auth_tokens (user_id, type, token_hash, expires_at)
            VALUES (:uid, :type, :hash, :expires)
        ", [
            ':uid'     => $userId,
            ':type'    => $type,
            ':hash'    => $tokenHash,
            ':expires' => $expiresAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findValidToken(string $tokenHash, string $type): array|false
    {
        return $this->db->selectOne("
            SELECT * FROM auth_tokens
            WHERE token_hash = :hash AND type = :type AND expires_at > NOW() AND used_at IS NULL
        ", [':hash' => $tokenHash, ':type' => $type]);
    }

    public function markTokenUsed(int $tokenId): void
    {
        $this->db->update('UPDATE auth_tokens SET used_at = NOW() WHERE id = :id', [':id' => $tokenId]);
    }

    /**
     * Marks every still-usable token of the given type as used, so a freshly
     * issued token is the only one that works (e.g. re-sending a verification email).
     */
    public function invalidatePendingTokens(int $userId, string $type): void
    {
        $this->db->statement(
            "UPDATE auth_tokens SET used_at = NOW() WHERE user_id = :uid AND type = :type AND used_at IS NULL",
            [':uid' => $userId, ':type' => $type]
        );
    }

    /**
     * Timestamp of the most recently issued token of this type (used or not),
     * for cooldown/rate-limiting a "resend" action. Null if none exist yet.
     */
    public function lastTokenIssuedAt(int $userId, string $type): ?string
    {
        $row = $this->db->selectOne(
            "SELECT created_at FROM auth_tokens WHERE user_id = :uid AND type = :type ORDER BY created_at DESC LIMIT 1",
            [':uid' => $userId, ':type' => $type]
        );
        return $row ? $row['created_at'] : null;
    }

    /**
     * Finds a token by hash regardless of expiry/used state, so callers can
     * distinguish "already verified" from "genuinely invalid" when a link fails.
     */
    public function findTokenAnyState(string $tokenHash, string $type): array|false
    {
        return $this->db->selectOne(
            "SELECT * FROM auth_tokens WHERE token_hash = :hash AND type = :type",
            [':hash' => $tokenHash, ':type' => $type]
        );
    }

    public function recordLoginAttempt(string $identifier, string $ip, bool $success): void
    {
        $this->db->insert(
            'INSERT INTO login_attempts (identifier, ip_address, success) VALUES (:id, :ip, :ok)',
            [':id' => $identifier, ':ip' => $ip, ':ok' => (int)$success]
        );
    }

    public function countRecentLoginAttempts(string $identifier, string $ip, int $windowSeconds): int
    {
        $row = $this->db->selectOne("
            SELECT COUNT(*) AS cnt FROM login_attempts
            WHERE (identifier = :id OR ip_address = :ip)
              AND attempted_at > DATE_SUB(NOW(), INTERVAL :window SECOND)
              AND success = 0
        ", [':id' => $identifier, ':ip' => $ip, ':window' => $windowSeconds]);
        return (int)($row['cnt'] ?? 0);
    }
}
