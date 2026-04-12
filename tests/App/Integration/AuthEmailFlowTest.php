<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Auth\Token\AuthTokenRepository;
use Waaseyaa\Database\DBALDatabase;

#[CoversNothing]
final class AuthEmailFlowTest extends TestCase
{
    private AuthTokenRepository $tokenRepo;

    protected function setUp(): void
    {
        $db = DBALDatabase::createSqlite(':memory:');
        $this->tokenRepo = new AuthTokenRepository($db, 'test-secret');
        $this->tokenRepo->ensureSchema();
    }

    #[Test]
    public function verification_token_lifecycle_activates_user(): void
    {
        $userId = 'user-integration-1';
        $token = $this->tokenRepo->createToken($userId, 'email_verification', 86400);

        $result = $this->tokenRepo->validateToken($token, 'email_verification');
        self::assertNotNull($result);
        self::assertSame($userId, $result['user_id']);

        $this->tokenRepo->consumeToken($result['id']);

        self::assertNull($this->tokenRepo->validateToken($token, 'email_verification'));
    }

    #[Test]
    public function new_token_invalidates_previous_one(): void
    {
        $userId = 'user-integration-2';

        $token1 = $this->tokenRepo->createToken($userId, 'email_verification', 86400);
        $token2 = $this->tokenRepo->createToken($userId, 'email_verification', 86400);

        self::assertNull($this->tokenRepo->validateToken($token1, 'email_verification'));

        $result2 = $this->tokenRepo->validateToken($token2, 'email_verification');
        self::assertNotNull($result2);
        self::assertSame($userId, $result2['user_id']);
    }
}
