<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Create the reaction table for engagement reactions (emoji, user, target).
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        $connection = $schema->getConnection();

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS reaction (
                rid INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid TEXT,
                emoji TEXT NOT NULL,
                user_id INTEGER NOT NULL,
                target_type TEXT NOT NULL,
                target_id INTEGER NOT NULL,
                created_at INTEGER NOT NULL DEFAULT 0
            )
        SQL);

        $connection->executeStatement(
            'CREATE INDEX IF NOT EXISTS idx_reaction_target ON reaction (target_type, target_id)',
        );

        $connection->executeStatement(
            'CREATE INDEX IF NOT EXISTS idx_reaction_user ON reaction (user_id)',
        );
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->getConnection()->executeStatement('DROP TABLE IF EXISTS reaction');
    }
};
