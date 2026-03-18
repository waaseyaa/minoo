<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Add is_municipality boolean column to community table.
 *
 * is_municipality provides a clean boolean split used by the /communities
 * filter UI (?type=first-nations / ?type=municipalities).
 *
 * Replaces: migrations/001_add_is_municipality.sql
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('community') && !$schema->hasColumn('community', 'is_municipality')) {
            $schema->getConnection()->executeStatement(
                'ALTER TABLE community ADD COLUMN is_municipality INTEGER NOT NULL DEFAULT 0',
            );
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        // SQLite does not support DROP COLUMN before 3.35.0.
        // For safety, this is a no-op.
    }
};
