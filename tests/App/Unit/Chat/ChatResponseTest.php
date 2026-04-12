<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat;

use App\Chat\ChatResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChatResponse::class)]
final class ChatResponseTest extends TestCase
{
    #[Test]
    public function okCreatesSuccessResponse(): void
    {
        $response = ChatResponse::ok('Hello there!');

        $this->assertTrue($response->success);
        $this->assertSame('Hello there!', $response->content);
        $this->assertSame('', $response->error);
    }

    #[Test]
    public function failCreatesErrorResponse(): void
    {
        $response = ChatResponse::fail('Something broke');

        $this->assertFalse($response->success);
        $this->assertSame('', $response->content);
        $this->assertSame('Something broke', $response->error);
    }
}
