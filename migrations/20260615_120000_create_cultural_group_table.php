<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Create the cultural_group table (#821). The entity type is registered for
 * the relaunch but its table never existed locally. Content-entity _data CLOB
 * shape; all field values live in the _data JSON blob, never dedicated columns.
 */
return new class () extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('cultural_group')) {
            return;
        }

        $schema->getConnection()->executeStatement('
            CREATE TABLE cultural_group (
                cgid INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid CLOB,
                bundle CLOB,
                name CLOB,
                langcode CLOB,
                _data CLOB
            )
        ');
    }

    public function down(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('cultural_group')) {
            $schema->getConnection()->executeStatement('DROP TABLE cultural_group');
        }
    }
};
