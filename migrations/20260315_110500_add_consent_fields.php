<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Add consent_public and consent_ai_training to entity types missing them.
 *
 * Default: consent_public=1 (public), consent_ai_training=0 (opt-in only).
 * Entity types affected: event, group, cultural_group,
 * cultural_collection, resource_person, leader.
 *
 * Replaces: migrations/002_add_consent_fields.sql
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'event',
        'group',
        'cultural_group',
        'cultural_collection',
        'resource_person',
        'leader',
    ];

    public function up(SchemaBuilder $schema): void
    {
        $connection = $schema->getConnection();

        foreach (self::TABLES as $table) {
            if (!$schema->hasTable($table)) {
                continue;
            }

            $quotedTable = $table === 'group' ? '"group"' : $table;

            if (!$schema->hasColumn($table, 'consent_public')) {
                $connection->executeStatement(
                    "ALTER TABLE {$quotedTable} ADD COLUMN consent_public INTEGER NOT NULL DEFAULT 1",
                );
            }

            if (!$schema->hasColumn($table, 'consent_ai_training')) {
                $connection->executeStatement(
                    "ALTER TABLE {$quotedTable} ADD COLUMN consent_ai_training INTEGER NOT NULL DEFAULT 0",
                );
            }
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        // SQLite does not support DROP COLUMN before 3.35.0.
        // For safety, this is a no-op.
    }
};
