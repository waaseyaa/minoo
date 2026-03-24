<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Create the crossword_puzzle config entity table.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('crossword_puzzle')) {
            return;
        }

        $schema->getConnection()->executeStatement("
            CREATE TABLE crossword_puzzle (
                id TEXT PRIMARY KEY,
                bundle CLOB,
                langcode CLOB,
                _data CLOB
            )
        ");
    }

    public function down(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('crossword_puzzle')) {
            $schema->getConnection()->executeStatement('DROP TABLE crossword_puzzle');
        }
    }
};
