<?php

declare(strict_types=1);

namespace App\Domain\Newsletter\Service;

class RenderTokenStore
{
    public function __construct(
        private readonly string $storageDir,
        private readonly int $ttlSeconds = 60,
    ) {
        if (! is_dir($storageDir) && ! @mkdir($storageDir, 0775, true) && ! is_dir($storageDir)) {
            throw new \RuntimeException("RenderTokenStore cannot create storage dir: {$storageDir}");
        }
    }

    public function issue(int $editionId): string
    {
        $token = bin2hex(random_bytes(16));
        $payload = json_encode([
            'edition_id' => $editionId,
            'expires_at' => time() + $this->ttlSeconds,
        ]);
        file_put_contents($this->path($token), $payload);
        return $token;
    }

    public function consume(string $token, int $editionId): bool
    {
        if (! preg_match('/^[a-f0-9]+$/', $token)) {
            return false;
        }
        $path = $this->path($token);
        if (! is_file($path)) {
            return false;
        }
        $payload = json_decode((string) file_get_contents($path), true);
        @unlink($path);

        if (! is_array($payload)) {
            return false;
        }
        if ((int) ($payload['edition_id'] ?? 0) !== $editionId) {
            return false;
        }
        if ((int) ($payload['expires_at'] ?? 0) < time()) {
            return false;
        }
        return true;
    }

    private function path(string $token): string
    {
        if (! preg_match('/^[a-f0-9]+$/', $token)) {
            throw new \InvalidArgumentException('Invalid token format');
        }
        return $this->storageDir . '/' . $token . '.json';
    }
}
