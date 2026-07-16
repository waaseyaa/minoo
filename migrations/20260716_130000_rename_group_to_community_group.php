<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Rename entity type `group` -> `community_group` (#923), table included.
 *
 * The framework derives the SQL table name unconditionally from the type id
 * (SqlSchemaHandler.php:63, SqlEntityStorage.php:113, SqlEntityQuery.php:127)
 * with no override mechanism, so the table must move with the id. This
 * migration is SHAPE-AWARE: PROD's `waaseyaa_migrations` ledger falsely
 * records the 20260419 extract migration as ran (mark-without-execute batch
 * of 2026-06-12), so no prior shape can be assumed from the ledger — only
 * hasTable/hasColumn guards. Both known shapes converge on one canonical
 * layout: core columns + `_data` blob, all business fields folded in.
 *
 *  - PROD (pre-extract):  6 legacy real columns on the base table
 *    (social_posts, consent_public, consent_ai_training, latitude,
 *    longitude, coordinate_source) + an EMPTY DBAL-shaped `group__business`.
 *    Fold the 6 columns into `_data`, drop them, drop the empty subtable.
 *  - LOCAL (post-extract): business rows live in `group__business`.
 *    Fold every non-NULL subtable column back into the base row's `_data`
 *    (reverse of the 20260419 extract), drop the subtable.
 *
 * Common tail: rename the table, converge the bundle index naming with what
 * a fresh schema-sync would produce (CREATE INDEX IF NOT EXISTS is
 * load-bearing — indexes survive RENAME TO under their old names, so a
 * down()->up() cycle would otherwise crash on "index already exists"), drop
 * the shape-forked `group_type` (0 rows, de-registered in #920), and
 * retarget every reference site. Two storage shapes, two statement forms —
 * never mixed: `reaction`/`comment`/`featured_item` keep the ref inside the
 * `_data` JSON blob (json_set); `follow.target_type` (materialized label-key
 * column), `search_metadata.entity_type` (table has NO `_data` column at
 * all), `audit_event.entity_type_id`, and `embeddings.entity_type` are REAL
 * columns (plain UPDATE).
 *
 * down() is rename-back only: index swap, rename, full retargeting reversal.
 * It deliberately does NOT resurrect the legacy columns or subtable — after
 * the fold, sql-blob serves everything from `_data`, so pre-rename code runs
 * correctly against the folded shape. Primary rollback is the file-level DB
 * restore, not down().
 *
 * The `group_uuid` UNIQUE constraint name is baked into the DDL and stays
 * (documented cosmetic drift; renaming it would require a full table
 * rebuild). Requires SQLite >= 3.35.0 for ALTER TABLE DROP COLUMN.
 */
return new class () extends Migration {
    /** @var list<string> Legacy Minoo-added base columns folded then dropped (PROD shape). */
    private const LEGACY_BASE_COLUMNS = [
        'social_posts',
        'consent_public',
        'consent_ai_training',
        'latitude',
        'longitude',
        'coordinate_source',
    ];

    /** @var list<string> `group__business` columns folded back into `_data` (LOCAL shape). */
    private const SUBTABLE_COLUMNS = [
        'slug',
        'description',
        'url',
        'region',
        'community_id',
        'phone',
        'email',
        'address',
        'booking_url',
        'media_id',
        'copyright_status',
        'consent_public',
        'consent_ai_training',
        'social_posts',
        'source',
        'verified_at',
        'coordinate_source',
        'latitude',
        'longitude',
    ];

    /** @var list<array{string, string}> Tables whose ref lives inside the `_data` JSON blob. */
    private const JSON_REF_SITES = [
        ['reaction', 'target_type'],
        ['comment', 'target_type'],
        ['featured_item', 'entity_type'],
    ];

    /** @var list<array{string, string}> Tables whose ref is a REAL column — plain UPDATEs only. */
    private const COLUMN_REF_SITES = [
        ['follow', 'target_type'],
        ['search_metadata', 'entity_type'],
        ['audit_event', 'entity_type_id'],
        ['embeddings', 'entity_type'],
    ];

    public function up(SchemaBuilder $schema): void
    {
        $connection = $schema->getConnection();

        $this->guardSqliteVersion($connection);

        if (!$schema->hasTable('group')) {
            // Already migrated (community_group exists) or a DB that never
            // had the table — no-op either way.
            return;
        }

        if ($schema->hasTable('community_group')) {
            throw new \RuntimeException(
                'Migration rename_group_to_community_group refuses to run: both "group" and '
                . '"community_group" exist (half-state). Investigate before re-running.',
            );
        }

        if ($schema->hasColumn('group', 'consent_public')) {
            // PROD shape (pre-extract). The subtable must be empty here —
            // rows would mean an unknown shape: abort loudly before mutating.
            if ($schema->hasTable('group__business')) {
                $count = (int) $connection->fetchOne('SELECT COUNT(*) FROM group__business');
                if ($count > 0) {
                    throw new \RuntimeException(sprintf(
                        'Migration rename_group_to_community_group found the PROD shape (legacy '
                        . 'columns on "group") but group__business has %d rows — unknown shape, refusing.',
                        $count,
                    ));
                }
            }

            $this->foldLegacyColumnsIntoData($connection);

            foreach (self::LEGACY_BASE_COLUMNS as $column) {
                if ($schema->hasColumn('group', $column)) {
                    $connection->executeStatement(sprintf('ALTER TABLE "group" DROP COLUMN %s', $column));
                }
            }
        } elseif ($schema->hasTable('group__business')) {
            // LOCAL shape (post-extract): fold the subtable back into _data.
            $this->foldSubtableIntoData($connection);
        }

        if ($schema->hasTable('group__business')) {
            $connection->executeStatement('DROP TABLE group__business');
        }

        // Common tail. sqlite_sequence.name auto-follows the rename.
        $connection->executeStatement('ALTER TABLE "group" RENAME TO community_group');
        $connection->executeStatement('DROP INDEX IF EXISTS group_bundle');
        $connection->executeStatement(
            'CREATE INDEX IF NOT EXISTS community_group_bundle ON community_group ("type")',
        );

        $connection->executeStatement('DROP TABLE IF EXISTS group_type');

        $this->retargetReferences($schema, 'group', 'community_group');
    }

    public function down(SchemaBuilder $schema): void
    {
        $connection = $schema->getConnection();

        if (!$schema->hasTable('community_group')) {
            return;
        }

        // Indexes survive RENAME TO under their old names: drop first so a
        // later up() re-run cannot collide and the renamed-back table does
        // not carry a misnamed index.
        $connection->executeStatement('DROP INDEX IF EXISTS community_group_bundle');
        $connection->executeStatement('ALTER TABLE community_group RENAME TO "group"');
        $connection->executeStatement('CREATE INDEX IF NOT EXISTS group_bundle ON "group" ("type")');

        // Rename-back only: the fold and group_type are NOT resurrected —
        // sql-blob serves the folded _data shape to pre-rename code.
        $this->retargetReferences($schema, 'community_group', 'group');
    }

    private function guardSqliteVersion(\Doctrine\DBAL\Connection $connection): void
    {
        $sqliteVersion = (string) $connection->fetchOne('SELECT sqlite_version()');
        if (version_compare($sqliteVersion, '3.35.0', '<')) {
            throw new \RuntimeException(sprintf(
                'Migration rename_group_to_community_group requires SQLite >= 3.35.0 for '
                . 'ALTER TABLE DROP COLUMN; detected %s.',
                $sqliteVersion,
            ));
        }
    }

    /**
     * PROD shape: merge the 6 legacy real columns into each row's `_data`.
     * Consent columns are NOT NULL with defaults — always folded; the other
     * four only when non-NULL. No key collisions exist (all observed PROD
     * `_data` key variants contain none of the six). Fold-before-drop is
     * required: registered fields are served from real columns while those
     * exist; once absent, sql-blob serves `_data`.
     */
    private function foldLegacyColumnsIntoData(\Doctrine\DBAL\Connection $connection): void
    {
        $rows = $connection->fetchAllAssociative(
            'SELECT gid, _data, social_posts, consent_public, consent_ai_training,'
            . ' latitude, longitude, coordinate_source FROM "group"',
        );

        foreach ($rows as $row) {
            /** @var array<string, mixed> $data */
            $data = json_decode((string) $row['_data'], true, 512, JSON_THROW_ON_ERROR);

            $data['consent_public'] = (int) $row['consent_public'];
            $data['consent_ai_training'] = (int) $row['consent_ai_training'];
            if ($row['social_posts'] !== null) {
                $data['social_posts'] = (string) $row['social_posts'];
            }
            if ($row['latitude'] !== null) {
                $data['latitude'] = (float) $row['latitude'];
            }
            if ($row['longitude'] !== null) {
                $data['longitude'] = (float) $row['longitude'];
            }
            if ($row['coordinate_source'] !== null) {
                $data['coordinate_source'] = (string) $row['coordinate_source'];
            }

            $connection->executeStatement(
                'UPDATE "group" SET _data = ? WHERE gid = ?',
                [json_encode($data, JSON_THROW_ON_ERROR), $row['gid']],
            );
        }
    }

    /**
     * LOCAL shape: reverse of the 20260419 extract — per gid, merge every
     * non-NULL business column back into the base row's `_data` (base `_data`
     * is only created_at/updated_at/status there — no collisions). Values are
     * preserved verbatim under their SQLite column affinities.
     */
    private function foldSubtableIntoData(\Doctrine\DBAL\Connection $connection): void
    {
        $subtableRows = $connection->fetchAllAssociative('SELECT * FROM group__business');

        foreach ($subtableRows as $subtableRow) {
            $baseData = $connection->fetchOne(
                'SELECT _data FROM "group" WHERE gid = ?',
                [$subtableRow['gid']],
            );
            if ($baseData === false) {
                continue;
            }

            /** @var array<string, mixed> $data */
            $data = json_decode((string) $baseData, true, 512, JSON_THROW_ON_ERROR);

            foreach (self::SUBTABLE_COLUMNS as $column) {
                if (array_key_exists($column, $subtableRow) && $subtableRow[$column] !== null) {
                    $data[$column] = $subtableRow[$column];
                }
            }

            $connection->executeStatement(
                'UPDATE "group" SET _data = ? WHERE gid = ?',
                [json_encode($data, JSON_THROW_ON_ERROR), $subtableRow['gid']],
            );
        }
    }

    /**
     * Defensive retargeting — every count is 0 in both environments today;
     * the statements are cheap insurance against rows created between the
     * 2026-07-16 snapshot and the actual cutover. Two storage shapes, two
     * statement forms — do not mix them (adversarial review F1/F2/F6).
     */
    private function retargetReferences(SchemaBuilder $schema, string $from, string $to): void
    {
        $connection = $schema->getConnection();

        foreach (self::JSON_REF_SITES as [$table, $key]) {
            if (!$schema->hasTable($table)) {
                continue;
            }
            $connection->executeStatement(
                sprintf(
                    "UPDATE %s SET _data = json_set(_data, '$.%s', ?) WHERE json_extract(_data, '$.%s') = ?",
                    $table,
                    $key,
                    $key,
                ),
                [$to, $from],
            );
        }

        foreach (self::COLUMN_REF_SITES as [$table, $column]) {
            if (!$schema->hasTable($table)) {
                continue;
            }
            $connection->executeStatement(
                sprintf('UPDATE %s SET %s = ? WHERE %s = ?', $table, $column, $column),
                [$to, $from],
            );
        }
    }
};
