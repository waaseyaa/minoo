<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Add latitude, longitude, and coordinate_source columns to the group table.
 *
 * These columns support map integration for business pages.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        if (!$schema->hasTable('group')) {
            return;
        }

        $connection = $schema->getConnection();

        if (!$schema->hasColumn('group', 'latitude')) {
            $connection->executeStatement(
                'ALTER TABLE "group" ADD COLUMN latitude REAL',
            );
        }

        if (!$schema->hasColumn('group', 'longitude')) {
            $connection->executeStatement(
                'ALTER TABLE "group" ADD COLUMN longitude REAL',
            );
        }

        if (!$schema->hasColumn('group', 'coordinate_source')) {
            $connection->executeStatement(
                'ALTER TABLE "group" ADD COLUMN coordinate_source TEXT',
            );
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        // SQLite does not support DROP COLUMN before 3.35.0.
        // For safety, this is a no-op.
    }
};
