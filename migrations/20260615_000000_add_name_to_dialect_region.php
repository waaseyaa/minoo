<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Add the `name` label column to dialect_region (#809).
 *
 * The dialect_region config entity declares label key `name`, but the table
 * created by 20260612_160000 only had code/bundle/langcode/_data — so
 * `schema:check` reported drift. The table is empty, so this is a clean ALTER.
 */
return new class () extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('dialect_region') && !$schema->hasColumn('dialect_region', 'name')) {
            $schema->getConnection()->executeStatement(
                'ALTER TABLE dialect_region ADD COLUMN name CLOB',
            );
        }
    }

    public function down(SchemaBuilder $schema): void
    {
    }
};
