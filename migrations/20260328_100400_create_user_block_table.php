<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Create the user_block table for user blocking subsystem.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('user_block')) {
            return;
        }

        $schema->getConnection()->executeStatement("
            CREATE TABLE user_block (
                ubid INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid CLOB,
                bundle CLOB,
                blocker_id CLOB,
                langcode CLOB,
                _data CLOB
            )
        ");
    }

    public function down(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('user_block')) {
            $schema->getConnection()->executeStatement('DROP TABLE user_block');
        }
    }
};
