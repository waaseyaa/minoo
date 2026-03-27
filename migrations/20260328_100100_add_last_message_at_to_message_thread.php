<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        if (!$schema->hasTable('message_thread')) {
            return;
        }

        if (!$schema->hasColumn('message_thread', 'last_message_at')) {
            $schema->getConnection()->executeStatement(
                'ALTER TABLE message_thread ADD COLUMN last_message_at INTEGER DEFAULT 0',
            );
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        // SQLite does not support DROP COLUMN before 3.35.0.
    }
};
