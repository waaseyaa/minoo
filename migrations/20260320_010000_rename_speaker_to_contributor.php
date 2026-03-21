<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Rename speaker table to contributors and update related references.
 *
 * - Renames table `speaker` → `contributors`
 * - Renames column `sid` → `coid`
 * - Adds new columns: role, community_id, consent_public, consent_record
 * - Renames `example_sentence.speaker_id` → `contributor_id`
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        $connection = $schema->getConnection();

        // Rename speaker table to contributors
        if ($schema->hasTable('speaker')) {
            $connection->executeStatement('ALTER TABLE speaker RENAME TO contributors');
        }

        // Rename sid → coid
        if ($schema->hasTable('contributors') && $schema->hasColumn('contributors', 'sid')) {
            $connection->executeStatement('ALTER TABLE contributors RENAME COLUMN sid TO coid');
        }

        // Add new columns
        if ($schema->hasTable('contributors')) {
            if (!$schema->hasColumn('contributors', 'role')) {
                $connection->executeStatement(
                    "ALTER TABLE contributors ADD COLUMN role TEXT DEFAULT NULL",
                );
            }

            if (!$schema->hasColumn('contributors', 'community_id')) {
                $connection->executeStatement(
                    "ALTER TABLE contributors ADD COLUMN community_id INTEGER DEFAULT NULL",
                );
            }

            if (!$schema->hasColumn('contributors', 'consent_public')) {
                $connection->executeStatement(
                    "ALTER TABLE contributors ADD COLUMN consent_public INTEGER NOT NULL DEFAULT 0",
                );
            }

            if (!$schema->hasColumn('contributors', 'consent_record')) {
                $connection->executeStatement(
                    "ALTER TABLE contributors ADD COLUMN consent_record INTEGER NOT NULL DEFAULT 0",
                );
            }
        }

        // Rename example_sentence.speaker_id → contributor_id
        if ($schema->hasTable('example_sentence') && $schema->hasColumn('example_sentence', 'speaker_id')) {
            $connection->executeStatement(
                'ALTER TABLE example_sentence RENAME COLUMN speaker_id TO contributor_id',
            );
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        // SQLite RENAME COLUMN requires 3.25.0+. Reverse is best-effort.
        $connection = $schema->getConnection();

        if ($schema->hasTable('example_sentence') && $schema->hasColumn('example_sentence', 'contributor_id')) {
            $connection->executeStatement(
                'ALTER TABLE example_sentence RENAME COLUMN contributor_id TO speaker_id',
            );
        }

        if ($schema->hasTable('contributors')) {
            $connection->executeStatement('ALTER TABLE contributors RENAME TO speaker');
        }

        if ($schema->hasTable('speaker') && $schema->hasColumn('speaker', 'coid')) {
            $connection->executeStatement('ALTER TABLE speaker RENAME COLUMN coid TO sid');
        }

        // Drop columns added by up() — SQLite 3.35+ supports DROP COLUMN
        if ($schema->hasTable('speaker')) {
            foreach (['role', 'community_id', 'consent_public', 'consent_record', 'cultural_group_id', 'clan', 'lineage_notes', 'photo'] as $col) {
                if ($schema->hasColumn('speaker', $col)) {
                    $connection->executeStatement(sprintf('ALTER TABLE speaker DROP COLUMN %s', $col));
                }
            }
        }
    }
};
