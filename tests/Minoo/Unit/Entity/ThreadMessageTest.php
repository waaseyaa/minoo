<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\ThreadMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ThreadMessage::class)]
final class ThreadMessageTest extends TestCase
{
    #[Test]
    public function it_creates_with_valid_body_and_defaults(): void
    {
        $before = time();

        $message = new ThreadMessage([
            'thread_id' => 10,
            'sender_id' => 2,
            'body' => 'Hello world',
        ]);

        $after = time();

        $this->assertSame(10, (int) $message->get('thread_id'));
        $this->assertSame(2, (int) $message->get('sender_id'));
        $this->assertSame('Hello world', $message->get('body'));
        $this->assertSame(1, (int) $message->get('status'));
        $this->assertGreaterThanOrEqual($before, (int) $message->get('created_at'));
        $this->assertLessThanOrEqual($after, (int) $message->get('created_at'));
    }

    #[Test]
    public function constructor_requires_thread_id_sender_id_body(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ThreadMessage([]);
    }

    #[Test]
    public function constructor_rejects_empty_body(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ThreadMessage([
            'thread_id' => 1,
            'sender_id' => 1,
            'body' => '   ',
        ]);
    }

    #[Test]
    public function constructor_rejects_oversized_body(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ThreadMessage([
            'thread_id' => 1,
            'sender_id' => 1,
            'body' => str_repeat('a', 2001),
        ]);
    }
}

