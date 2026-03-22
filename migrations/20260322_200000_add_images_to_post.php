<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Add images column to the post table for storing JSON array of image paths.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        $connection = $schema->getConnection();

        $connection->executeStatement(<<<'SQL'
            ALTER TABLE post ADD COLUMN images TEXT DEFAULT NULL
        SQL);
    }

    public function down(SchemaBuilder $schema): void
    {
        // SQLite does not support DROP COLUMN before 3.35.0.
        // For older versions, this is a no-op.
        $connection = $schema->getConnection();

        try {
            $connection->executeStatement(<<<'SQL'
                ALTER TABLE post DROP COLUMN images
            SQL);
        } catch (\PDOException) {
            // Ignore if SQLite version doesn't support DROP COLUMN
        }
    }
};
