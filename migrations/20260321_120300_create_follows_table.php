<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Create the follow table for user follow relationships.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        $connection = $schema->getConnection();

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS follow (
                fid INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid TEXT,
                user_id INTEGER NOT NULL,
                target_type TEXT NOT NULL,
                target_id INTEGER NOT NULL,
                created_at INTEGER NOT NULL DEFAULT 0
            )
        SQL);

        $connection->executeStatement(
            'CREATE INDEX IF NOT EXISTS idx_follow_user ON follow (user_id)',
        );

        $connection->executeStatement(
            'CREATE INDEX IF NOT EXISTS idx_follow_target ON follow (target_type, target_id)',
        );

        $connection->executeStatement(
            'CREATE UNIQUE INDEX IF NOT EXISTS idx_follow_unique ON follow (user_id, target_type, target_id)',
        );
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->getConnection()->executeStatement('DROP TABLE IF EXISTS follow');
    }
};
