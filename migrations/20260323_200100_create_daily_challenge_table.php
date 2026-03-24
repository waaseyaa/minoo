<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Create the daily_challenge table for Ishkode word game.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('daily_challenge')) {
            return;
        }

        $schema->getConnection()->executeStatement("
            CREATE TABLE daily_challenge (
                date TEXT PRIMARY KEY,
                bundle CLOB,
                langcode CLOB,
                _data CLOB
            )
        ");
    }

    public function down(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('daily_challenge')) {
            $schema->getConnection()->executeStatement('DROP TABLE daily_challenge');
        }
    }
};
