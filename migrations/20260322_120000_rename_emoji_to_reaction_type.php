<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Rename the emoji column to reaction_type in the reaction table.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        $schema->getConnection()->executeStatement(
            'ALTER TABLE reaction RENAME COLUMN emoji TO reaction_type',
        );
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->getConnection()->executeStatement(
            'ALTER TABLE reaction RENAME COLUMN reaction_type TO emoji',
        );
    }
};
