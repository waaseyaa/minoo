<?php

declare(strict_types=1);

namespace Minoo\Middleware;

final class RateLimitMiddleware
{
    private \PDO $pdo;

    public function __construct(string $dbPath)
    {
        $this->pdo = new \PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->ensureTable();
    }

    public function check(string $ip, string $path, int $maxAttempts, int $windowSeconds): bool
    {
        $since = time() - $windowSeconds;
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND path = ? AND created_at > ?');
        $stmt->execute([$ip, $path, $since]);
        return (int) $stmt->fetchColumn() < $maxAttempts;
    }

    public function record(string $ip, string $path): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO login_attempts (ip, path, created_at) VALUES (?, ?, ?)');
        $stmt->execute([$ip, $path, time()]);
    }

    private function ensureTable(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS login_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT, ip TEXT NOT NULL, path TEXT NOT NULL, created_at INTEGER NOT NULL)');
    }
}
