<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Entity;

use Minoo\Entity\ThreadParticipant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ThreadParticipant::class)]
final class ThreadParticipantTest extends TestCase
{
    #[Test]
    public function it_creates_with_defaults(): void
    {
        $before = time();

        $participant = new ThreadParticipant([
            'thread_id' => 10,
            'user_id' => 5,
            'thread_creator_id' => 99,
        ]);

        $after = time();

        $this->assertSame('member', $participant->get('role'));
        $this->assertSame(10, (int) $participant->get('thread_id'));
        $this->assertSame(5, (int) $participant->get('user_id'));
        $this->assertGreaterThanOrEqual($before, (int) $participant->get('joined_at'));
        $this->assertLessThanOrEqual($after, (int) $participant->get('joined_at'));
        $this->assertSame(0, (int) $participant->get('last_read_at'));
    }

    #[Test]
    public function constructor_requires_thread_id_and_user_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ThreadParticipant([]);
    }

    #[Test]
    public function constructor_rejects_invalid_role(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ThreadParticipant([
            'thread_id' => 1,
            'user_id' => 2,
            'thread_creator_id' => 1,
            'role' => 'bogus',
        ]);
    }

    #[Test]
    public function it_exposes_entity_type_id(): void
    {
        $participant = new ThreadParticipant([
            'thread_id' => 1,
            'user_id' => 2,
            'thread_creator_id' => 1,
        ]);

        $this->assertSame('thread_participant', $participant->getEntityTypeId());
    }
}

