<?php

declare(strict_types=1);

namespace App\Infrastructure\Entity;

use Waaseyaa\Entity\DateTime\TimestampFieldConvention;
use Waaseyaa\Entity\EntityTypeManagerInterface;
use Waaseyaa\Entity\Event\EntityEvent;

/**
 * PRE_SAVE listener that populates created_at / updated_at for every entity
 * type that declares them as timestamp fields (generalized from the former
 * game_session-only listener).
 *
 * Rationale: Waaseyaa alpha.266 diverges the EntityRepository save path from
 * SqlEntityStorage — the repository does NOT auto-populate timestamp fields.
 * Populating them app-side on PRE_SAVE keeps every save path covered.
 *
 * Compatibility with the current engine (alpha.249): SqlEntityStorage::save()
 * runs populateTimestamps() BEFORE dispatching PRE_SAVE, with set-if-empty
 * semantics for the 'create' role (only new entities with a raw-unset value
 * are filled). This listener mirrors that: created_at is set only when
 * raw-unset (per TimestampFieldConvention::isRawTimestampUnset), so listener
 * and engine never fight over an existing value; updated_at is always
 * refreshed, exactly as the engine's 'update' role and the previous
 * game_session listener did. Values are unix ints via time(), the app-wide
 * Minoo convention for *_at fields.
 */
final class TimestampPreSaveListener
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {
    }

    public function __invoke(EntityEvent $event): void
    {
        $entity = $event->entity;

        try {
            $type = $this->entityTypeManager->getDefinition($entity->getEntityTypeId());
        } catch (\InvalidArgumentException) {
            // Unregistered type (e.g. framework-internal saves) — nothing to do.
            return;
        }

        $fields = $type->getFieldDefinitions();
        $now = time();

        $created = $fields['created_at'] ?? null;
        if ($created !== null
            && $created->getType() === 'timestamp'
            && TimestampFieldConvention::isRawTimestampUnset($entity, 'created_at')
        ) {
            $entity->set('created_at', $now);
        }

        $updated = $fields['updated_at'] ?? null;
        if ($updated !== null && $updated->getType() === 'timestamp') {
            $entity->set('updated_at', $now);
        }
    }
}
