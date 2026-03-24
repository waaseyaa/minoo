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
                uuid CLOB,
                bundle CLOB,
                mode CLOB,
                langcode CLOB,
                _data CLOB
            )
        ");
    }

    public function down(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('game_session')) {
            $schema->getConnection()->executeStatement('DROP TABLE game_session');
        }
    }
};
