<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Publish the consented corpus (Phase 4 consent grant, applied on deploy).
 *
 * The production database was seeded with the 27 Steven Bennett corpus rows
 * (example_sentence) + the speaker with ALL consent flags OFF and unpublished,
 * pending written consent. Consent has now been granted for public display,
 * publication, search, AI grounding, and AI training, so this flips the
 * existing rows ON. Idempotent: re-running sets the same values; if a fresh
 * import already wrote them ON (scripts/import-corpus.php), this is a no-op.
 *
 * Values live in the _data JSON blob, so json_set is used. No row is created
 * here and nothing from the corpus directory is read; only the consent state of
 * already-present rows changes.
 */
return new class () extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $connection = $schema->getConnection();

        $connection->executeStatement(
            "UPDATE example_sentence
             SET _data = json_set(_data, '$.consent_public', 1, '$.consent_ai_training', 1, '$.status', 1)
             WHERE json_extract(_data, '$.source_sentence_id') LIKE 'corpus:%'",
        );

        $connection->executeStatement(
            "UPDATE speaker
             SET _data = json_set(_data, '$.consent_public_display', 1, '$.consent_ai_training', 1, '$.status', 1)
             WHERE json_extract(_data, '$.code') = 'sb'",
        );
    }

    public function down(SchemaBuilder $schema): void
    {
        $connection = $schema->getConnection();

        $connection->executeStatement(
            "UPDATE example_sentence
             SET _data = json_set(_data, '$.consent_public', 0, '$.consent_ai_training', 0, '$.status', 0)
             WHERE json_extract(_data, '$.source_sentence_id') LIKE 'corpus:%'",
        );

        $connection->executeStatement(
            "UPDATE speaker
             SET _data = json_set(_data, '$.consent_public_display', 0, '$.consent_ai_training', 0, '$.status', 0)
             WHERE json_extract(_data, '$.code') = 'sb'",
        );
    }
};
