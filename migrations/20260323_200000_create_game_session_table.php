<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Create the game_session table for Ishkode word game.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('game_session')) {
            return;
        }

        $schema->getConnection()->executeStatement("
            CREATE TABLE game_session (
                gsid INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid TEXT NOT NULL,
                mode TEXT NOT NULL,
                direction TEXT NOT NULL,
                dictionary_entry_id INTEGER NOT NULL,
                user_id INTEGER DEFAULT NULL,
                guesses TEXT DEFAULT '[]',
                wrong_count INTEGER DEFAULT 0,
                status TEXT DEFAULT 'in_progress',
                daily_date TEXT DEFAULT NULL,
                difficulty_tier TEXT DEFAULT 'easy',
                created_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL
            )
        ");

        $schema->getConnection()->executeStatement(
            'CREATE INDEX idx_game_session_user ON game_session (user_id)',
        );
        $schema->getConnection()->executeStatement(
            'CREATE INDEX idx_game_session_daily ON game_session (daily_date, user_id)',
        );
    }

    public function down(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('game_session')) {
            $schema->getConnection()->executeStatement('DROP TABLE game_session');
        }
    }
};
