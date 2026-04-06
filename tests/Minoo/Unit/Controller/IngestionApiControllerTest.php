<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\IngestionApiController;
use Minoo\Entity\IngestLog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(IngestionApiController::class)]
final class IngestionApiControllerTest extends TestCase
{
    #[Test]
    public function status_returns_null_when_file_missing(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $controller = new IngestionApiController($etm);
        $account = $this->createMock(AccountInterface::class);
        $response = $controller->status([], [], $account, new HttpRequest());

        $this->assertSame(200, $response->getStatusCode());
        $decoded = json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('status', $decoded);
    }

    #[Test]
    public function ingest_envelope_requires_valid_json_body(): void
    {
        $etm = $this->createMock(EntityTypeManager::class);
        $controller = new IngestionApiController($etm);
        $account = $this->createMock(AccountInterface::class);
        $request = new HttpRequest(content: 'not-json');

        $response = $controller->ingestEnvelope([], [], $account, $request);

        $this->assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function approve_updates_pending_review_log(): void
    {
        $log = new IngestLog([
            'ilid' => 10,
            'title' => 'Test',
            'status' => 'pending_review',
            'created_at' => 1700000000,
            'updated_at' => 1700000000,
        ]);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('load')->with(10)->willReturn($log);
        $storage->expects($this->once())->method('save')->with($log);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('ingest_log')->willReturn($storage);

        $account = $this->createMock(AccountInterface::class);
        $account->method('id')->willReturn(99);

        $controller = new IngestionApiController($etm);
        $response = $controller->approve(['id' => '10'], [], $account, new HttpRequest());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('approved', $log->get('status'));
        $this->assertSame(99, $log->get('reviewed_by'));
    }
}
