<?php

declare(strict_types=1);

namespace Minoo\Ingestion;

use Minoo\Ingestion\EntityMapper\NcArticleToEventMapper;
use Minoo\Ingestion\EntityMapper\NcArticleToTeachingMapper;
use Minoo\Support\NorthCloudClient;
use Waaseyaa\Entity\EntityTypeManager;

class NcContentSyncService
{
    public function __construct(
        private readonly NorthCloudClient $client,
        private readonly EntityTypeManager $entityTypeManager,
        private readonly NcArticleToTeachingMapper $teachingMapper = new NcArticleToTeachingMapper(),
        private readonly NcArticleToEventMapper $eventMapper = new NcArticleToEventMapper(),
    ) {}

    /**
     * Pull recent indigenous content from NC Search API and create Minoo entities.
     */
    public function sync(int $limit = 20, ?string $since = null, bool $dryRun = false): NcSyncResult
    {
        $response = $this->client->getRecentContent($limit, $since);

        if ($response === null) {
            error_log('NcContentSyncService: failed to fetch content from NorthCloud');
            return (new NcSyncResult())->withFetchFailed();
        }

        $result = new NcSyncResult();

        foreach ($response['hits'] as $hit) {
            $result = $this->processHit($hit, $dryRun, $result);
        }

        return $result;
    }

    private function processHit(array $hit, bool $dryRun, NcSyncResult $result): NcSyncResult
    {
        $sourceUrl = (string) ($hit['url'] ?? '');
        if ($sourceUrl === '') {
            return $result->withFailed();
        }

        $isEvent = $this->isEventContent($hit);
        $entityType = $isEvent ? 'event' : 'teaching';

        // Dedup check
        $storage = $this->entityTypeManager->getStorage($entityType);
        $existing = $storage->getQuery()
            ->condition('source_url', $sourceUrl)
            ->execute();

        if ($existing !== []) {
            return $result->withSkipped();
        }

        if ($dryRun) {
            return $result->withCreated();
        }

        $fields = $isEvent
            ? $this->eventMapper->map($hit)
            : $this->teachingMapper->map($hit);

        try {
            $entity = $storage->create($fields);
            $storage->save($entity);
            return $result->withCreated();
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            error_log(sprintf('NcContentSyncService: failed to create %s from %s: %s', $entityType, $sourceUrl, $e->getMessage()));
            return $result->withFailed();
        }
    }

    private function isEventContent(array $hit): bool
    {
        $topics = $hit['topics'] ?? [];
        if (is_array($topics) && in_array('event', $topics, true)) {
            return true;
        }

        $contentType = (string) ($hit['content_type'] ?? '');
        return $contentType === 'event';
    }
}
