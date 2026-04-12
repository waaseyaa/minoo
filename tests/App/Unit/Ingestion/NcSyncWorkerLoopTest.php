<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingestion;

use App\Ingestion\NcContentSyncService;
use App\Ingestion\NcSyncResult;
use App\Ingestion\NcSyncWorkerLoop;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NcSyncWorkerLoop::class)]
final class NcSyncWorkerLoopTest extends TestCase
{
    #[Test]
    public function single_cycle_calls_sync_and_writes_status(): void
    {
        $syncService = $this->createMock(NcContentSyncService::class);
        $syncService->method('sync')->willReturn(new NcSyncResult(created: 3, skipped: 7));

        $statusPath = sys_get_temp_dir() . '/nc-sync-test-' . uniqid() . '.json';

        $loop = new NcSyncWorkerLoop(
            syncService: $syncService,
            statusPath: $statusPath,
            intervalSeconds: 1,
            maxCycles: 1,
        );
        $loop->run();

        $this->assertFileExists($statusPath);
        $status = json_decode(file_get_contents($statusPath), true);
        $this->assertSame(3, $status['created']);
        $this->assertSame(7, $status['skipped']);
        $this->assertSame(0, $status['failed']);
        $this->assertFalse($status['fetch_failed']);
        $this->assertSame(1, $status['cycles']);
        $this->assertArrayHasKey('last_sync', $status);

        @unlink($statusPath);
    }

    #[Test]
    public function max_cycles_stops_loop(): void
    {
        $syncService = $this->createMock(NcContentSyncService::class);
        $syncService->expects($this->exactly(3))->method('sync')
            ->willReturn(new NcSyncResult(created: 1));

        $statusPath = sys_get_temp_dir() . '/nc-sync-test-' . uniqid() . '.json';

        $loop = new NcSyncWorkerLoop(
            syncService: $syncService,
            statusPath: $statusPath,
            intervalSeconds: 0,
            maxCycles: 3,
        );
        $loop->run();

        $status = json_decode(file_get_contents($statusPath), true);
        $this->assertSame(3, $status['cycles']);

        @unlink($statusPath);
    }

    #[Test]
    public function stop_terminates_loop(): void
    {
        $callCount = 0;
        $loop = null;

        $syncService = $this->createMock(NcContentSyncService::class);
        $syncService->method('sync')->willReturnCallback(function () use (&$callCount, &$loop) {
            $callCount++;
            if ($callCount >= 2) {
                $loop->stop();
            }
            return new NcSyncResult(created: 1);
        });

        $statusPath = sys_get_temp_dir() . '/nc-sync-test-' . uniqid() . '.json';

        $loop = new NcSyncWorkerLoop(
            syncService: $syncService,
            statusPath: $statusPath,
            intervalSeconds: 0,
            maxCycles: 0,
        );
        $loop->run();

        $this->assertSame(2, $callCount);

        @unlink($statusPath);
    }

    #[Test]
    public function fetch_failed_is_recorded_in_status(): void
    {
        $syncService = $this->createMock(NcContentSyncService::class);
        $syncService->method('sync')
            ->willReturn(new NcSyncResult(fetchFailed: true));

        $statusPath = sys_get_temp_dir() . '/nc-sync-test-' . uniqid() . '.json';

        $loop = new NcSyncWorkerLoop(
            syncService: $syncService,
            statusPath: $statusPath,
            intervalSeconds: 1,
            maxCycles: 1,
        );
        $loop->run();

        $status = json_decode(file_get_contents($statusPath), true);
        $this->assertTrue($status['fetch_failed']);

        @unlink($statusPath);
    }

    #[Test]
    public function sync_exception_is_caught_and_loop_continues(): void
    {
        $callCount = 0;
        $syncService = $this->createMock(NcContentSyncService::class);
        $syncService->method('sync')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                throw new \RuntimeException('Connection refused');
            }
            return new NcSyncResult(created: 1);
        });

        $statusPath = sys_get_temp_dir() . '/nc-sync-test-' . uniqid() . '.json';

        $loop = new NcSyncWorkerLoop(
            syncService: $syncService,
            statusPath: $statusPath,
            intervalSeconds: 0,
            maxCycles: 2,
        );
        $loop->run();

        $status = json_decode(file_get_contents($statusPath), true);
        $this->assertSame(2, $status['cycles']);
        $this->assertSame(1, $status['created']);

        @unlink($statusPath);
    }
}
