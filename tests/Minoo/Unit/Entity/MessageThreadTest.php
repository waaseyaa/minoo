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
}

