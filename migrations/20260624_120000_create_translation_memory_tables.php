<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Create the language-module translation-memory tables (issue #890):
 * translation_memory and tm_gap_log, both content entities.
 *
 * Content-entity schema is the framework _data CLOB shape; all field values live
 * in the _data JSON blob, never in dedicated columns. The label key column is
 * source_en for both. The :memory: test kernel auto-creates these from the entity
 * definitions; this migration gives production the same tables.
 */
return new class () extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $connection = $schema->getConnection();

        if (!$schema->hasTable('translation_memory')) {
            $connection->executeStatement('
                CREATE TABLE translation_memory (
                    tmid INTEGER PRIMARY KEY AUTOINCREMENT,
                    uuid CLOB,
                    bundle CLOB,
                    source_en CLOB,
                    langcode CLOB,
                    _data CLOB
                )
            ');
        }

        if (!$schema->hasTable('tm_gap_log')) {
            $connection->executeStatement('
                CREATE TABLE tm_gap_log (
                    glid INTEGER PRIMARY KEY AUTOINCREMENT,
                    uuid CLOB,
                    bundle CLOB,
                    source_en CLOB,
                    langcode CLOB,
                    _data CLOB
                )
            ');
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        foreach (['translation_memory', 'tm_gap_log'] as $table) {
            if ($schema->hasTable($table)) {
                $schema->getConnection()->executeStatement('DROP TABLE ' . $table);
            }
        }
    }
};
