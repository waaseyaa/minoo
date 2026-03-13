<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Service;

use Minoo\Service\FlashMessageService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlashMessageService::class)]
final class FlashMessageServiceTest extends TestCase
{
    private FlashMessageService $service;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->service = new FlashMessageService();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    #[Test]
    public function addSuccessStoresMessage(): void
    {
        $this->service->addSuccess('Done.');

        $messages = $this->service->consumeAll();

        self::assertCount(1, $messages);
        self::assertSame('success', $messages[0]['type']);
        self::assertSame('Done.', $messages[0]['message']);
    }

    #[Test]
    public function addErrorStoresMessage(): void
    {
        $this->service->addError('Failed.');

        $messages = $this->service->consumeAll();

        self::assertCount(1, $messages);
        self::assertSame('error', $messages[0]['type']);
        self::assertSame('Failed.', $messages[0]['message']);
    }

    #[Test]
    public function addInfoStoresMessage(): void
    {
        $this->service->addInfo('Note this.');

        $messages = $this->service->consumeAll();

        self::assertCount(1, $messages);
        self::assertSame('info', $messages[0]['type']);
        self::assertSame('Note this.', $messages[0]['message']);
    }

    #[Test]
    public function consumeAllReturnsEmptyArrayWhenNoMessages(): void
    {
        self::assertSame([], $this->service->consumeAll());
    }

    #[Test]
    public function consumeAllClearsMessagesAfterReading(): void
    {
        $this->service->addSuccess('First.');

        $this->service->consumeAll();
        $second = $this->service->consumeAll();

        self::assertSame([], $second);
    }

    #[Test]
    public function multipleMessagesArePreservedInOrder(): void
    {
        $this->service->addSuccess('First.');
        $this->service->addError('Second.');
        $this->service->addInfo('Third.');

        $messages = $this->service->consumeAll();

        self::assertCount(3, $messages);
        self::assertSame('success', $messages[0]['type']);
        self::assertSame('error', $messages[1]['type']);
        self::assertSame('info', $messages[2]['type']);
    }

    #[Test]
    public function consumeAllFiltersCorruptedSessionData(): void
    {
        $_SESSION['flash_messages'] = [
            ['type' => 'success', 'message' => 'Valid.'],
            'not-an-array',
            ['type' => 'error'],
            ['type' => 'info', 'message' => ''],
            ['type' => 'success', 'message' => 'Also valid.'],
        ];

        $messages = $this->service->consumeAll();

        self::assertCount(2, $messages);
        self::assertSame('Valid.', $messages[0]['message']);
        self::assertSame('Also valid.', $messages[1]['message']);
    }

    #[Test]
    public function consumeAllFiltersUnknownTypes(): void
    {
        $_SESSION['flash_messages'] = [
            ['type' => 'success', 'message' => 'Good.'],
            ['type' => 'warning', 'message' => 'Unknown type.'],
            ['type' => 'danger', 'message' => 'Also unknown.'],
            ['type' => 'error', 'message' => 'Bad.'],
        ];

        $messages = $this->service->consumeAll();

        self::assertCount(2, $messages);
        self::assertSame('Good.', $messages[0]['message']);
        self::assertSame('Bad.', $messages[1]['message']);
    }
}
