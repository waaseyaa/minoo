<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Create the saved_word table — a member's personal word list (#806).
 *
 * Content-entity _data CLOB shape: user_id, dictionary_entry_id, slug,
 * definition and created_at live in the _data JSON blob; the headword is
 * denormalised into the `word` label column so "My words" renders without
 * re-loading every dictionary entry.
 */
return new class () extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        if (!$schema->hasTable('saved_word')) {
            $schema->getConnection()->executeStatement('
                CREATE TABLE saved_word (
                    swid INTEGER PRIMARY KEY AUTOINCREMENT,
                    uuid CLOB,
                    bundle CLOB,
                    word CLOB,
                    langcode CLOB,
                    _data CLOB
                )
            ');
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        // Personal data — intentionally no destructive down().
    }
};
