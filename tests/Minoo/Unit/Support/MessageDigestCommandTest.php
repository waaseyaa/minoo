<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Support;

use Minoo\Support\MailService;
use Minoo\Support\MessageDigestCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(MessageDigestCommand::class)]
final class MessageDigestCommandTest extends TestCase
{
    private EntityTypeManager $etm;
    private MailService $mailService;

    protected function setUp(): void
    {
        $this->etm = $this->createMock(EntityTypeManager::class);
        $this->mailService = $this->createMock(MailService::class);
    }

    #[Test]
    public function it_skips_when_mail_not_configured(): void
    {
        $this->mailService->method('isConfigured')->willReturn(false);
        $this->mailService->expects($this->never())->method('sendPlain');

        $command = new MessageDigestCommand($this->etm, $this->mailService, []);
        $command->execute();
    }

    #[Test]
    public function it_skips_users_with_no_unread(): void
    {
        $this->mailService->method('isConfigured')->willReturn(true);
        $this->mailService->expects($this->never())->method('sendPlain');

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

        $command = new MessageDigestCommand($this->etm, $this->mailService, []);
        $command->execute();
    }
}
