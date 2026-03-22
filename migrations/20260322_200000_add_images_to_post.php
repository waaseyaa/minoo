<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Add images column to the post table for image upload support.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        $schema->getConnection()->executeStatement(
            "ALTER TABLE post ADD COLUMN images TEXT DEFAULT '[]'",
        );
    }

    public function down(SchemaBuilder $schema): void
    {
        // SQLite < 3.35 doesn't support DROP COLUMN
    }
};
