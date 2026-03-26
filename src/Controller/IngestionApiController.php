<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Ingestion\IngestImporter;
use Minoo\Ingestion\IngestMaterializer;
use Minoo\Ingestion\IngestStatus;
use Minoo\Ingestion\PayloadValidator;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class IngestionApiController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    public function status(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $status = $this->readNcStatusFile();
        if ($status === null) {
            return $this->json(['status' => null]);
        }

        return $this->json(['status' => $status]);
    }

    public function ingestEnvelope(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $payload = $this->jsonBody($request);
        if ($payload === []) {
            return $this->json(['error' => 'Request body must be valid JSON envelope.'], 422);
        }

        $importer = new IngestImporter(new PayloadValidator());
        $log = $importer->import($payload);
        $log->set('created_at', time());
        $log->set('updated_at', time());

        $storage = $this->entityTypeManager->getStorage('ingest_log');
        $storage->save($log);

        return $this->json([
            'id' => $log->id(),
            'status' => $log->get('status'),
            'title' => $log->label(),
        ], 201);
    }

    public function approve(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        return $this->updateStatus((int) ($params['id'] ?? 0), IngestStatus::Approved->value, (int) $account->id());
    }

    public function reject(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        return $this->updateStatus((int) ($params['id'] ?? 0), 'rejected', (int) $account->id());
    }

    public function materialize(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->json(['error' => 'Invalid id.'], 422);
        }

        $storage = $this->entityTypeManager->getStorage('ingest_log');
        $log = $storage->load($id);
        if ($log === null) {
            return $this->json(['error' => 'Not found.'], 404);
        }

        if ((string) $log->get('status') !== IngestStatus::Approved->value) {
            return $this->json(['error' => 'Only approved logs can be materialized.'], 422);
        }

        $materializer = new IngestMaterializer($this->entityTypeManager);

        try {
            $result = $materializer->materialize($log);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        $primaryEntityId = $result->getPrimaryEntityId();
        if ($primaryEntityId !== null) {
            $log->set('entity_id', $primaryEntityId);
        }
        $log->set('updated_at', time());
        $storage->save($log);

        return $this->json([
            'materialized' => true,
            'created' => count($result->getCreated()),
            'updated' => count($result->getUpdated()),
            'skipped' => count($result->getSkipped()),
            'primary_entity_id' => $result->getPrimaryEntityId(),
        ]);
    }

    private function updateStatus(int $id, string $status, int $reviewedBy): SsrResponse
    {
        if ($id <= 0) {
            return $this->json(['error' => 'Invalid id.'], 422);
        }

        $storage = $this->entityTypeManager->getStorage('ingest_log');
        $log = $storage->load($id);
        if ($log === null) {
            return $this->json(['error' => 'Not found.'], 404);
        }

        $currentStatus = (string) $log->get('status');
        if ($currentStatus !== IngestStatus::PendingReview->value) {
            return $this->json(['error' => 'Only pending_review logs can be reviewed.'], 422);
        }

        $log->set('status', $status);
        $log->set('reviewed_by', $reviewedBy);
        $log->set('reviewed_at', time());
        $log->set('updated_at', time());
        $storage->save($log);

        return $this->json([
            'id' => $log->id(),
            'status' => $log->get('status'),
            'reviewed_at' => $log->get('reviewed_at'),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function readNcStatusFile(): ?array
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

    /** @return array<string, mixed> */
    private function jsonBody(HttpRequest $request): array
    {
        $content = $request->getContent();
        if ($content === '' || $content === false) {
            return [];
        }

        try {
            return (array) json_decode((string) $content, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
    }

    /** @param array<string, mixed> $data */
    private function json(array $data, int $status = 200): SsrResponse
    {
        return new SsrResponse(
            content: json_encode($data, JSON_THROW_ON_ERROR),
            statusCode: $status,
            headers: ['Content-Type' => 'application/json'],
        );
    }
}
