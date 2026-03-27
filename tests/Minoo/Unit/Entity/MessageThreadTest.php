<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\MessageThread;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageThread::class)]
final class MessageThreadTest extends TestCase
{
    #[Test]
    public function it_creates_with_defaults(): void
    {
        $before = time();

        $thread = new MessageThread([
            'created_by' => 123,
        ]);

        $after = time();

        $this->assertSame('', $thread->get('title'));
        $this->assertSame(123, $thread->get('created_by'));
        $this->assertGreaterThanOrEqual($before, $thread->get('created_at'));
        $this->assertLessThanOrEqual($after, $thread->get('created_at'));
        $this->assertSame($thread->get('created_at'), $thread->get('updated_at'));
    }

    #[Test]
    public function constructor_requires_created_by(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MessageThread([]);
    }

    #[Test]
    public function it_exposes_entity_type_id(): void
    {
        $thread = new MessageThread(['created_by' => 1]);
        $this->assertSame('message_thread', $thread->getEntityTypeId());
    }

    #[Test]
    public function it_defaults_thread_type_to_direct(): void
    {
        $thread = new MessageThread(['created_by' => 1]);
        $this->assertSame('direct', $thread->get('thread_type'));
    }

    #[Test]
    public function it_accepts_group_thread_type(): void
    {
        $thread = new MessageThread(['created_by' => 1, 'thread_type' => 'group']);
        $this->assertSame('group', $thread->get('thread_type'));
    }

    #[Test]
    public function it_rejects_invalid_thread_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('thread_type must be direct or group');
        new MessageThread(['created_by' => 1, 'thread_type' => 'invalid']);
    }

    #[Test]
    public function it_defaults_last_message_at_to_created_at(): void
    {
        $thread = new MessageThread(['created_by' => 1]);
        $this->assertSame($thread->get('created_at'), $thread->get('last_message_at'));
    }
}

