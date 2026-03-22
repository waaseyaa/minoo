<?php

declare(strict_types=1);

namespace Minoo\Support;

final class EmailVerificationService
{
    private bool $tableEnsured = false;

    public function __construct(private readonly \PDO $pdo) {}

    /**
     * Create a verification token for a user. Invalidates any existing token for that user.
     * Returns the 64-char hex token string.
     */
    public function createToken(int|string $userId): string
    {
        $this->ensureTable();
        // Delete existing tokens for this user
        $stmt = $this->pdo->prepare('DELETE FROM email_verification_tokens WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);
        // Generate new token
        $token = bin2hex(random_bytes(32)); // 64-char hex
        $stmt = $this->pdo->prepare(
            'INSERT INTO email_verification_tokens (token, user_id, expires_at, used_at) VALUES (:token, :uid, :expires, NULL)'
        );
        $stmt->execute([
            'token' => $token,
            'uid' => $userId,
            'expires' => time() + 86400, // 24 hours
        ]);

        return $token;
    }

    /**
     * Validate a token. Returns user_id if valid, null otherwise.
     * Valid = exists, not expired, not used.
     */
    public function validateToken(string $token): int|string|null
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare(
            'SELECT user_id FROM email_verification_tokens WHERE token = :token AND expires_at > :now AND used_at IS NULL'
        );
        $stmt->execute(['token' => $token, 'now' => time()]);
        $result = $stmt->fetchColumn();

        return $result !== false ? $result : null;
    }

    /**
     * Mark a token as used (consumed).
     */
    public function consumeToken(string $token): void
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare('UPDATE email_verification_tokens SET used_at = :now WHERE token = :token');
        $stmt->execute(['token' => $token, 'now' => time()]);
    }

    private function ensureTable(): void
    {
        if ($this->tableEnsured) {
            return;
        }
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS email_verification_tokens ('
            . 'token TEXT PRIMARY KEY, '
            . 'user_id TEXT NOT NULL, '
            . 'expires_at INTEGER NOT NULL, '
            . 'used_at INTEGER'
            . ')'
        );
        $this->tableEnsured = true;
    }
}
