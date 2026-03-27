<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        if (!$schema->hasTable('thread_message')) {
            return;
        }

        if (!$schema->hasColumn('thread_message', 'deleted_at')) {
            $schema->getConnection()->executeStatement(
                'ALTER TABLE thread_message ADD COLUMN deleted_at INTEGER DEFAULT NULL',
            );
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        // SQLite does not support DROP COLUMN before 3.35.0.
    }
};
