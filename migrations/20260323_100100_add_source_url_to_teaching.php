<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Add source_url column to the teaching table for NC content deduplication.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        $schema->getConnection()->executeStatement(
            'ALTER TABLE teaching ADD COLUMN source_url TEXT DEFAULT NULL',
        );
    }

    public function down(SchemaBuilder $schema): void
    {
        // SQLite < 3.35 doesn't support DROP COLUMN
    }
};
