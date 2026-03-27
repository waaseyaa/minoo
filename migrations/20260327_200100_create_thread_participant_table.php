<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Create the thread_participant table for messaging thread membership.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('thread_participant')) {
            return;
        }

        $schema->getConnection()->executeStatement("
            CREATE TABLE thread_participant (
                tpid INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid CLOB,
                bundle CLOB,
                role CLOB,
                langcode CLOB,
                _data CLOB
            )
        ");
    }

    public function down(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('thread_participant')) {
            $schema->getConnection()->executeStatement('DROP TABLE thread_participant');
        }
    }
};
