<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration;

use Minoo\Support\EmailVerificationService;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class AuthEmailFlowTest extends TestCase
{
    private \PDO $pdo;
    private EmailVerificationService $verifyService;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->verifyService = new EmailVerificationService($this->pdo);
    }

    #[Test]
    public function verification_token_lifecycle_activates_user(): void
    {
        // 1. Create a verification token (simulating registration)
        $userId = 'user-integration-1';
        $token = $this->verifyService->createToken($userId);

        // 2. Token should be valid
        self::assertSame($userId, $this->verifyService->validateToken($token));

        // 3. Consume the token (simulating verification click)
        $this->verifyService->consumeToken($token);

        // 4. Token should no longer be valid
        self::assertNull($this->verifyService->validateToken($token));
    }

    #[Test]
    public function new_token_invalidates_previous_one(): void
    {
        $userId = 'user-integration-2';

        $token1 = $this->verifyService->createToken($userId);
        $token2 = $this->verifyService->createToken($userId);

        // First token should be invalidated
        self::assertNull($this->verifyService->validateToken($token1));
        // Second token should be valid
        self::assertSame($userId, $this->verifyService->validateToken($token2));
    }
}
