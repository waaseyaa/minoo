<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Create the post table for user-generated posts.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        $connection = $schema->getConnection();

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS post (
                pid INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid TEXT,
                body TEXT NOT NULL,
                user_id INTEGER NOT NULL,
                status INTEGER NOT NULL DEFAULT 1,
                created_at INTEGER NOT NULL DEFAULT 0,
                updated_at INTEGER NOT NULL DEFAULT 0
            )
        SQL);

        $connection->executeStatement(
            'CREATE INDEX IF NOT EXISTS idx_post_user ON post (user_id)',
        );

        $connection->executeStatement(
            'CREATE INDEX IF NOT EXISTS idx_post_status ON post (status, created_at)',
        );
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->getConnection()->executeStatement('DROP TABLE IF EXISTS post');
    }
};
