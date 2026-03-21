<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Create the comment table for engagement comments (body, user, target).
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        $connection = $schema->getConnection();

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS comment (
                cid INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid TEXT,
                body TEXT NOT NULL,
                user_id INTEGER NOT NULL,
                target_type TEXT NOT NULL,
                target_id INTEGER NOT NULL,
                status INTEGER NOT NULL DEFAULT 1,
                created_at INTEGER NOT NULL DEFAULT 0
            )
        SQL);

        $connection->executeStatement(
            'CREATE INDEX IF NOT EXISTS idx_comment_target ON comment (target_type, target_id)',
        );

        $connection->executeStatement(
            'CREATE INDEX IF NOT EXISTS idx_comment_user ON comment (user_id)',
        );
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->getConnection()->executeStatement('DROP TABLE IF EXISTS comment');
    }
};
