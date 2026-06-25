<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Create the translation-backlog table (issue #906): tm_backlog, a content
 * entity holding the demand-ranked English worklist seeded from the public
 * website recon.
 *
 * Content-entity schema is the framework _data CLOB shape; all field values live
 * in the _data JSON blob, never in dedicated columns. The label key column is
 * english_text. The :memory: test kernel auto-creates this from the entity
 * definition; this migration gives production the same table.
 */
return new class () extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        if (!$schema->hasTable('tm_backlog')) {
            $schema->getConnection()->executeStatement('
                CREATE TABLE tm_backlog (
                    tbid INTEGER PRIMARY KEY AUTOINCREMENT,
                    uuid CLOB,
                    bundle CLOB,
                    english_text CLOB,
                    langcode CLOB,
                    _data CLOB
                )
            ');
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('tm_backlog')) {
            $schema->getConnection()->executeStatement('DROP TABLE tm_backlog');
        }
    }
};
