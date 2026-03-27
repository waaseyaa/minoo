<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Create the message_thread table for user messaging.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('message_thread')) {
            return;
        }

        $schema->getConnection()->executeStatement("
            CREATE TABLE message_thread (
                mtid INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid CLOB,
                bundle CLOB,
                title CLOB,
                langcode CLOB,
                _data CLOB
            )
        ");
    }

    public function down(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('message_thread')) {
            $schema->getConnection()->executeStatement('DROP TABLE message_thread');
        }
    }
};
