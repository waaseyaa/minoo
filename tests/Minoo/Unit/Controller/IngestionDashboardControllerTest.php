<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\IngestionDashboardController;
use Minoo\Entity\IngestLog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(IngestionDashboardController::class)]
final class IngestionDashboardControllerTest extends TestCase
{
    #[Test]
    public function index_reads_nc_sync_status_file_when_present(): void
    {
        $recentQuery = $this->queryMockReturning([], allowCondition: true);
        $pendingCountQuery = $this->queryMockReturning([0], count: true, allowCondition: true);
        $approvedCountQuery = $this->queryMockReturning([0], count: true, allowCondition: true);
        $rejectedCountQuery = $this->queryMockReturning([0], count: true, allowCondition: true);
        $failedCountQuery = $this->queryMockReturning([0], count: true, allowCondition: true);
        $totalCountQuery = $this->queryMockReturning([0], count: true);
        $lastSyncQuery = $this->queryMockReturning([]);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturnOnConsecutiveCalls(
            $recentQuery,
            $pendingCountQuery,
            $approvedCountQuery,
            $rejectedCountQuery,
            $failedCountQuery,
            $totalCountQuery,
            $lastSyncQuery,
        );
        $storage->method('loadMultiple')->willReturn([]);
        $storage->method('load')->willReturn(null);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('ingest_log')->willReturn($storage);

        $root = dirname(__DIR__, 4);
        $statusPath = $root . '/storage/nc-sync-status.json';
        $backupPath = null;
        if (is_file($statusPath)) {
            $backupPath = $statusPath . '.bak-' . uniqid();
            rename($statusPath, $backupPath);
        }
        file_put_contents($statusPath, json_encode([
            'last_sync' => '2026-03-26T12:00:00+00:00',
            'created' => 2,
            'skipped' => 3,
            'failed' => 1,
            'fetch_failed' => false,
            'cycles' => 7,
        ], JSON_THROW_ON_ERROR));

        try {
            $twig = $this->createMock(Environment::class);
            $twig->expects($this->once())
                ->method('render')
                ->with(
                    'admin/ingestion.html.twig',
                    $this->callback(fn (array $vars): bool => is_array($vars['nc_sync']) && ($vars['nc_sync']['created'] ?? null) === 2),
                )
                ->willReturn('<html>ok</html>');

            $controller = new IngestionDashboardController($etm, $twig);
            $account = $this->createMock(AccountInterface::class);
            $request = new HttpRequest();
            $response = $controller->index([], [], $account, $request);
            $this->assertSame(200, $response->statusCode);
        } finally {
            if (is_file($statusPath)) {
                unlink($statusPath);
            }
            if ($backupPath !== null && is_file($backupPath)) {
                rename($backupPath, $statusPath);
            }
        }
    }

    #[Test]
    public function index_renders_with_empty_logs(): void
    {
        $recentQuery = $this->queryMockReturning([], allowCondition: true);
        $pendingCountQuery = $this->queryMockReturning([0], count: true, allowCondition: true);
        $approvedCountQuery = $this->queryMockReturning([0], count: true, allowCondition: true);
        $rejectedCountQuery = $this->queryMockReturning([0], count: true, allowCondition: true);
        $failedCountQuery = $this->queryMockReturning([0], count: true, allowCondition: true);
        $totalCountQuery = $this->queryMockReturning([0], count: true);
        $lastSyncQuery = $this->queryMockReturning([]);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturnOnConsecutiveCalls(
            $recentQuery,
            $pendingCountQuery,
            $approvedCountQuery,
            $rejectedCountQuery,
            $failedCountQuery,
            $totalCountQuery,
            $lastSyncQuery,
        );
        $storage->method('loadMultiple')->willReturn([]);
        $storage->method('load')->willReturn(null);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('ingest_log')->willReturn($storage);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'admin/ingestion.html.twig',
                $this->callback(function (array $vars): bool {
                    return $vars['logs'] === []
                        && $vars['total_count'] === 0
                        && $vars['last_envelope_log'] === null
                        && $vars['nc_sync'] === null
                        && $vars['status_filter'] === null
                        && $vars['status_counts'] === [
                            'pending_review' => 0,
                            'approved' => 0,
                            'rejected' => 0,
                            'failed' => 0,
                        ]
                        && $vars['hide_sidebar'] === true;
                }),
            )
            ->willReturn('<html>ok</html>');

        $controller = new IngestionDashboardController($etm, $twig);
        $account = $this->createMock(AccountInterface::class);
        $request = new HttpRequest();

        $response = $controller->index([], [], $account, $request);

        $this->assertSame(200, $response->statusCode);
    }

    #[Test]
    public function index_filters_by_status(): void
    {
        $recentQuery = $this->queryMockReturning([7], allowCondition: true);
        $pendingCountQuery = $this->queryMockReturning([1], count: true, allowCondition: true);
        $approvedCountQuery = $this->queryMockReturning([2], count: true, allowCondition: true);
        $rejectedCountQuery = $this->queryMockReturning([3], count: true, allowCondition: true);
        $failedCountQuery = $this->queryMockReturning([4], count: true, allowCondition: true);
        $totalCountQuery = $this->queryMockReturning([10], count: true);
        $lastSyncQuery = $this->queryMockReturning([7]);

        $log = new IngestLog([
            'ilid' => 7,
            'title' => 'Failed import',
            'status' => 'failed',
            'created_at' => 1711468800,
        ]);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturnOnConsecutiveCalls(
            $recentQuery,
            $pendingCountQuery,
            $approvedCountQuery,
            $rejectedCountQuery,
            $failedCountQuery,
            $totalCountQuery,
            $lastSyncQuery,
        );
        $storage->method('loadMultiple')->with([7])->willReturn([7 => $log]);
        $storage->method('load')->with(7)->willReturn($log);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('ingest_log')->willReturn($storage);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'admin/ingestion.html.twig',
                $this->callback(fn (array $vars): bool => $vars['status_filter'] === 'failed' && $vars['total_count'] === 10 && $vars['last_envelope_log'] === 1711468800 && count($vars['logs']) === 1),
            )
            ->willReturn('<html>ok</html>');

        $controller = new IngestionDashboardController($etm, $twig);
        $account = $this->createMock(AccountInterface::class);
        $request = new HttpRequest();

        $response = $controller->index([], ['status' => 'failed'], $account, $request);

        $this->assertSame(200, $response->statusCode);
    }

    #[Test]
    public function index_rejects_invalid_status_filter(): void
    {
        $recentQuery = $this->queryMockReturning([], allowCondition: true);
        $pendingCountQuery = $this->queryMockReturning([0], count: true, allowCondition: true);
        $approvedCountQuery = $this->queryMockReturning([0], count: true, allowCondition: true);
        $rejectedCountQuery = $this->queryMockReturning([0], count: true, allowCondition: true);
        $failedCountQuery = $this->queryMockReturning([0], count: true, allowCondition: true);
        $totalCountQuery = $this->queryMockReturning([0], count: true);
        $lastSyncQuery = $this->queryMockReturning([]);

        $storage = $this->createMock(EntityStorageInterface::class);
        $storage->method('getQuery')->willReturnOnConsecutiveCalls(
            $recentQuery,
            $pendingCountQuery,
            $approvedCountQuery,
            $rejectedCountQuery,
            $failedCountQuery,
            $totalCountQuery,
            $lastSyncQuery,
        );
        $storage->method('loadMultiple')->willReturn([]);
        $storage->method('load')->willReturn(null);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->with('ingest_log')->willReturn($storage);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'admin/ingestion.html.twig',
                $this->callback(fn (array $vars): bool => $vars['status_filter'] === null),
            )
            ->willReturn('<html>ok</html>');

        $controller = new IngestionDashboardController($etm, $twig);
        $account = $this->createMock(AccountInterface::class);
        $request = new HttpRequest();

        $response = $controller->index([], ['status' => 'evil_injection'], $account, $request);

        $this->assertSame(200, $response->statusCode);
    }

    private function queryMockReturning(array $executeResult, bool $count = false, bool $allowCondition = false): EntityQueryInterface
    {
        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('sort')->willReturnSelf();
        $query->method('range')->willReturnSelf();
        if ($count) {
            $query->method('count')->willReturnSelf();
        }
        if ($allowCondition) {
            $query->method('condition')->willReturnSelf();
        }
        $query->method('execute')->willReturn($executeResult);

        return $query;
    }
}
