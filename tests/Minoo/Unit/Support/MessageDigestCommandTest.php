<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Support;

use Minoo\Support\MessageDigestCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Waaseyaa\Mail\MailerInterface;

#[CoversClass(MessageDigestCommand::class)]
final class MessageDigestCommandTest extends TestCase
{
    private EntityTypeManager $etm;

    private MailerInterface $mailer;

    protected function setUp(): void
    {
        $this->etm = $this->createMock(EntityTypeManager::class);
        $this->mailer = $this->createMock(MailerInterface::class);
    }

    #[Test]
    public function it_skips_when_mail_not_configured(): void
    {
        $this->mailer->expects($this->never())->method('send');

        $command = new MessageDigestCommand($this->etm, $this->mailer, false, []);
        $command->execute();
    }

    #[Test]
    public function it_skips_users_with_no_unread(): void
    {
        $this->mailer->expects($this->never())->method('send');

        $participantStorage = $this->createMock(EntityStorageInterface::class);
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('sort')->willReturn($query);
        $query->method('execute')->willReturn([]);
        $participantStorage->method('getQuery')->willReturn($query);

        $messageStorage = $this->createMock(EntityStorageInterface::class);
        $userStorage = $this->createMock(EntityStorageInterface::class);

        $this->etm->method('getStorage')->willReturnMap([
            ['thread_participant', $participantStorage],
            ['thread_message', $messageStorage],
            ['user', $userStorage],
        ]);

        $command = new MessageDigestCommand($this->etm, $this->mailer, true, []);
        $command->execute();
    }
}
