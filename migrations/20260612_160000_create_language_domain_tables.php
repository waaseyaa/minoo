<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Create the language-domain tables that never existed in production:
 * speaker, word_part, contributor (content entities) and dialect_region
 * (config entity, id = dialect code).
 *
 * Content-entity schema is the framework _data CLOB shape; all field
 * values live in the _data JSON blob, never in dedicated columns.
 */
return new class () extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $connection = $schema->getConnection();

        if (!$schema->hasTable('speaker')) {
            $connection->executeStatement('
                CREATE TABLE speaker (
                    spid INTEGER PRIMARY KEY AUTOINCREMENT,
                    uuid CLOB,
                    bundle CLOB,
                    name CLOB,
                    langcode CLOB,
                    _data CLOB
                )
            ');
        }

        if (!$schema->hasTable('word_part')) {
            $connection->executeStatement('
                CREATE TABLE word_part (
                    wpid INTEGER PRIMARY KEY AUTOINCREMENT,
                    uuid CLOB,
                    bundle CLOB,
                    form CLOB,
                    langcode CLOB,
                    _data CLOB
                )
            ');
        }

        if (!$schema->hasTable('contributor')) {
            $connection->executeStatement('
                CREATE TABLE contributor (
                    coid INTEGER PRIMARY KEY AUTOINCREMENT,
                    uuid CLOB,
                    bundle CLOB,
                    name CLOB,
                    langcode CLOB,
                    _data CLOB
                )
            ');
        }

        if (!$schema->hasTable('dialect_region')) {
            $connection->executeStatement('
                CREATE TABLE dialect_region (
                    code TEXT PRIMARY KEY,
                    bundle CLOB,
                    langcode CLOB,
                    _data CLOB
                )
            ');
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        foreach (['speaker', 'word_part', 'contributor', 'dialect_region'] as $table) {
            if ($schema->hasTable($table)) {
                $schema->getConnection()->executeStatement('DROP TABLE ' . $table);
            }
        }
    }
};
