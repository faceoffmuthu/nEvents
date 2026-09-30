<?php

declare(strict_types=1);

namespace NEvents\Core\Database;

use PDO;
use PDOStatement;

class Connection
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );
        $this->pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function select(string $sql, array $bindings = []): array
    {
        $stmt = $this->execute($sql, $bindings);
        return $stmt->fetchAll();
    }

    public function selectOne(string $sql, array $bindings = []): array|false
    {
        $stmt = $this->execute($sql, $bindings);
        return $stmt->fetch();
    }

    public function insert(string $sql, array $bindings = []): int
    {
        $this->execute($sql, $bindings);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(string $sql, array $bindings = []): int
    {
        $stmt = $this->execute($sql, $bindings);
        return $stmt->rowCount();
    }

    public function delete(string $sql, array $bindings = []): int
    {
        $stmt = $this->execute($sql, $bindings);
        return $stmt->rowCount();
    }

    public function statement(string $sql, array $bindings = []): bool
    {
        $stmt = $this->execute($sql, $bindings);
        return $stmt !== false;
    }

    public function execute(string $sql, array $bindings = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt;
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }
}
