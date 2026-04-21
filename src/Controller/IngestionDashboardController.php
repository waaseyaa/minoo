<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\LayoutTwigContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Symfony\Component\HttpFoundation\Response;

final class IngestionDashboardController
{
    private const array VALID_STATUSES = ['pending_review', 'approved', 'rejected', 'failed'];

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, string> $params */
    public function index(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $storage = $this->entityTypeManager->getStorage('ingest_log');

        $statusFilter = $this->resolveStatusFilter($query);
        $logs = $this->loadRecentLogs($storage, $statusFilter);
        $statusCounts = $this->buildStatusCounts($storage);

        $html = $this->twig->render('pages/admin/ingestion.html.twig', LayoutTwigContext::withAccount($account, [
            'logs' => $logs,
            'total_count' => $this->countLogs($storage),
            'status_counts' => $statusCounts,
            'last_envelope_log' => $this->loadLastSync($storage),
            'nc_sync' => $this->loadNcSyncStatus(),
            'status_filter' => $statusFilter,
            'hide_sidebar' => true,
            'path' => '/staff/ingestion',
        ]));

        return new Response($html);
    }

    private function resolveStatusFilter(array $query): ?string
    {
        $raw = $query['status'] ?? null;
        if (!is_string($raw)) {
            return null;
        }

        return in_array($raw, self::VALID_STATUSES, true) ? $raw : null;
    }

    private function loadRecentLogs(EntityStorageInterface $storage, ?string $statusFilter): array
    {
        $query = $storage->getQuery()
            ->sort('created_at', 'DESC');

        if ($statusFilter !== null) {
            $query->condition('status', $statusFilter);
        }

        $ids = $query->range(0, 50)->execute();
        if ($ids === []) {
            return [];
        }

        return array_values($storage->loadMultiple($ids));
    }

    /** @return array<string, int> */
    private function buildStatusCounts(EntityStorageInterface $storage): array
    {
        return [
            'pending_review' => $this->countLogs($storage, 'pending_review'),
            'approved' => $this->countLogs($storage, 'approved'),
            'rejected' => $this->countLogs($storage, 'rejected'),
            'failed' => $this->countLogs($storage, 'failed'),
        ];
    }

    private function countLogs(EntityStorageInterface $storage, ?string $status = null): int
    {
        $query = $storage->getQuery()->count();
        if ($status !== null) {
            $query->condition('status', $status);
        }

        $result = $query->execute();
        return isset($result[0]) ? (int) $result[0] : 0;
    }

    private function loadLastSync(EntityStorageInterface $storage): ?int
    {
        $ids = $storage->getQuery()->sort('created_at', 'DESC')->range(0, 1)->execute();
        if ($ids === []) {
            return null;
        }

        $latest = $storage->load(reset($ids));
        if ($latest === null) {
            return null;
        }

        $createdAt = $latest->get('created_at');
        return is_numeric($createdAt) ? (int) $createdAt : null;
    }

    /** @return array<string, mixed>|null */
    private function loadNcSyncStatus(): ?array
    {
        $statusPath = dirname(__DIR__, 2) . '/storage/nc-sync-status.json';
        if (!is_file($statusPath)) {
            return null;
        }

        $raw = file_get_contents($statusPath);
        if ($raw === false || $raw === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
