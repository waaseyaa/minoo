<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Create the thread_message table for messaging content.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('thread_message')) {
            return;
        }

        $schema->getConnection()->executeStatement("
            CREATE TABLE thread_message (
                tmid INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid CLOB,
                bundle CLOB,
                body CLOB,
                langcode CLOB,
                _data CLOB
            )
        ");
    }

    public function down(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('thread_message')) {
            $schema->getConnection()->executeStatement('DROP TABLE thread_message');
        }
    }
};
