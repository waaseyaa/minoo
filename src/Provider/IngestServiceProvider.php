<?php

declare(strict_types=1);

namespace App\Provider;

use App\Entity\IngestLog;
use App\Ingestion\NcContentSyncService;
use App\Support\Command\NcSyncCommand;
use App\Support\NorthCloudClient;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;

final class IngestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'ingest_log',
            label: 'Ingestion Log',
            class: IngestLog::class,
            keys: ['id' => 'ilid', 'uuid' => 'uuid', 'label' => 'title'],
            group: 'ingestion',
            fieldDefinitions: [
                'title' => [
                    'type' => 'string',
                    'label' => 'Title',
                    'weight' => 0,
                ],
                'status' => [
                    'type' => 'string',
                    'label' => 'Status',
                    'description' => 'pending_review, approved, rejected, or failed.',
                    'weight' => 1,
                    'default' => 'pending_review',
                ],
                'source' => [
                    'type' => 'string',
                    'label' => 'Source',
                    'description' => 'Origin identifier (e.g. northcloud, ojibwe_lib).',
                    'weight' => 2,
                ],
                'entity_type_target' => [
                    'type' => 'string',
                    'label' => 'Target Entity Type',
                    'description' => 'Entity type machine name for the parsed content.',
                    'weight' => 3,
                ],
                'entity_id' => [
                    'type' => 'integer',
                    'label' => 'Created Entity ID',
                    'description' => 'ID of the entity created after approval.',
                    'weight' => 4,
                ],
                'payload_raw' => [
                    'type' => 'text',
                    'label' => 'Raw Payload',
                    'description' => 'Original payload JSON from source.',
                    'weight' => 10,
                ],
                'payload_parsed' => [
                    'type' => 'text',
                    'label' => 'Parsed Payload',
                    'description' => 'Mapped/transformed fields JSON.',
                    'weight' => 11,
                ],
                'error_message' => [
                    'type' => 'text',
                    'label' => 'Error Message',
                    'description' => 'Error details if status is failed.',
                    'weight' => 12,
                ],
                'reviewed_by' => [
                    'type' => 'entity_reference',
                    'label' => 'Reviewed By',
                    'settings' => ['target_type' => 'user'],
                    'weight' => 20,
                ],
                'reviewed_at' => [
                    'type' => 'timestamp',
                    'label' => 'Reviewed At',
                    'weight' => 21,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                    'label' => 'Created',
                    'weight' => 40,
                ],
                'updated_at' => [
                    'type' => 'timestamp',
                    'label' => 'Updated',
                    'weight' => 41,
                ],
            ],
        ));
    }

    public function commands(
        EntityTypeManager $entityTypeManager,
        \Waaseyaa\Database\DatabaseInterface $database,
        \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher,
    ): array {
        $ncConfig = $this->config['northcloud'] ?? [];
        $baseUrl = $ncConfig['base_url'] ?? '';

        $searchTimeout = (int) ($this->config['search']['timeout'] ?? 15);
        $client = new NorthCloudClient(baseUrl: $baseUrl, timeout: $searchTimeout);
        $syncService = new NcContentSyncService($client, $entityTypeManager);

        return [
            new NcSyncCommand($syncService),
        ];
    }
}
