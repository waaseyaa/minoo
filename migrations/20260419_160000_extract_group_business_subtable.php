<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Extract group business-bundle fields into the waaseyaa/groups subtable shape.
 *
 * Migrates from Minoo's monolithic `group` table (6 Minoo-added columns + 13
 * keys smeared into `_data` JSON) to the framework's bundle-scoped layout:
 * all 19 business-bundle fields live in a dedicated `group__business` subtable
 * keyed to the base row via PK with ON DELETE CASCADE.
 *
 * Also reshapes the empty `group_type` config table to match the framework's
 * `Waaseyaa\Groups\GroupType` core keys (`id`, `label`) plus the registered
 * `description` field and the standard `_data` column.
 *
 * The 6 legacy base columns are DROPPED in this migration, not deferred.
 * Keeping them around would trigger a shadow-collision bug: bundle-routed
 * writes go to the subtable, but `SqlEntityStorage::mergeBundleSubtableRow`
 * resolves read-time collisions with base-wins — reads would return stale
 * base values, and new inserts would return base-column defaults instead of
 * the subtable values. DROP avoids that entirely.
 *
 * Assumes an offline / migration-paused DB at run time. No attempt is made
 * to tolerate concurrent writes during the backfill window.
 *
 * Requires SQLite >= 3.35.0 for ALTER TABLE DROP COLUMN.
 */
return new class extends Migration {
    /** @var list<string> JSON keys removed from base `_data` by this migration. */
    private const DATA_KEYS_MOVED_TO_SUBTABLE = [
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
        'source',
        'verified_at',
    ];

    /** @var list<string> Legacy Minoo-added base columns dropped by this migration. */
    private const LEGACY_BASE_COLUMNS = [
        'social_posts',
        'consent_public',
        'consent_ai_training',
        'latitude',
        'longitude',
        'coordinate_source',
    ];

    public function up(SchemaBuilder $schema): void
    {
        $connection = $schema->getConnection();

        $sqliteVersion = (string) $connection->fetchOne('SELECT sqlite_version()');
        if (version_compare($sqliteVersion, '3.35.0', '<')) {
            throw new \RuntimeException(sprintf(
                'Migration extract_group_business_subtable requires SQLite >= 3.35.0 for ALTER TABLE DROP COLUMN; detected %s.',
                $sqliteVersion,
            ));
        }

        $connection->executeStatement('
            CREATE TABLE group__business (
                gid INTEGER PRIMARY KEY,
                slug TEXT,
                description TEXT,
                url TEXT,
                region TEXT,
                community_id INTEGER,
                phone TEXT,
                email TEXT,
                address TEXT,
                booking_url TEXT,
                media_id INTEGER,
                copyright_status TEXT,
                consent_public INTEGER NOT NULL DEFAULT 1,
                consent_ai_training INTEGER NOT NULL DEFAULT 0,
                social_posts TEXT,
                source TEXT,
                verified_at TEXT,
                coordinate_source TEXT,
                latitude REAL,
                longitude REAL,
                FOREIGN KEY (gid) REFERENCES "group" (gid) ON DELETE CASCADE
            )
        ');

        $connection->executeStatement('
            INSERT INTO group__business (
                gid, slug, description, url, region, community_id,
                phone, email, address, booking_url, media_id, copyright_status,
                consent_public, consent_ai_training, social_posts,
                source, verified_at,
                coordinate_source, latitude, longitude
            )
            SELECT
                gid,
                json_extract(_data, \'$.slug\'),
                json_extract(_data, \'$.description\'),
                json_extract(_data, \'$.url\'),
                json_extract(_data, \'$.region\'),
                CAST(json_extract(_data, \'$.community_id\') AS INTEGER),
                json_extract(_data, \'$.phone\'),
                json_extract(_data, \'$.email\'),
                json_extract(_data, \'$.address\'),
                json_extract(_data, \'$.booking_url\'),
                CAST(json_extract(_data, \'$.media_id\') AS INTEGER),
                COALESCE(json_extract(_data, \'$.copyright_status\'), \'unknown\'),
                consent_public,
                consent_ai_training,
                social_posts,
                json_extract(_data, \'$.source\'),
                json_extract(_data, \'$.verified_at\'),
                coordinate_source,
                latitude,
                longitude
            FROM "group"
            WHERE type = \'business\'
        ');

        $jsonRemoveArgs = implode(', ', array_map(
            static fn (string $key) => sprintf("'$.%s'", $key),
            self::DATA_KEYS_MOVED_TO_SUBTABLE,
        ));
        $connection->executeStatement(sprintf(
            'UPDATE "group" SET _data = json_remove(_data, %s) WHERE type = \'business\'',
            $jsonRemoveArgs,
        ));

        foreach (self::LEGACY_BASE_COLUMNS as $column) {
            $connection->executeStatement(sprintf(
                'ALTER TABLE "group" DROP COLUMN %s',
                $column,
            ));
        }

        $connection->executeStatement('DROP TABLE group_type');
        $connection->executeStatement('
            CREATE TABLE group_type (
                id TEXT NOT NULL PRIMARY KEY,
                label TEXT NOT NULL DEFAULT \'\',
                description TEXT,
                _data TEXT NOT NULL DEFAULT \'{}\'
            )
        ');
    }

    public function down(SchemaBuilder $schema): void
    {
        $connection = $schema->getConnection();

        // Dev-workflow rollback. Bundle values in group__business are dropped
        // (not migrated back into base _data); re-running up() from fresh
        // fixtures is the intended path.

        if ($schema->hasTable('group__business')) {
            $connection->executeStatement('DROP TABLE group__business');
        }

        if ($schema->hasTable('group')) {
            if (!$schema->hasColumn('group', 'social_posts')) {
                $connection->executeStatement('ALTER TABLE "group" ADD COLUMN social_posts TEXT');
            }
            if (!$schema->hasColumn('group', 'consent_public')) {
                $connection->executeStatement('ALTER TABLE "group" ADD COLUMN consent_public INTEGER NOT NULL DEFAULT 1');
            }
            if (!$schema->hasColumn('group', 'consent_ai_training')) {
                $connection->executeStatement('ALTER TABLE "group" ADD COLUMN consent_ai_training INTEGER NOT NULL DEFAULT 0');
            }
            if (!$schema->hasColumn('group', 'latitude')) {
                $connection->executeStatement('ALTER TABLE "group" ADD COLUMN latitude REAL');
            }
            if (!$schema->hasColumn('group', 'longitude')) {
                $connection->executeStatement('ALTER TABLE "group" ADD COLUMN longitude REAL');
            }
            if (!$schema->hasColumn('group', 'coordinate_source')) {
                $connection->executeStatement('ALTER TABLE "group" ADD COLUMN coordinate_source TEXT');
            }
        }

        if ($schema->hasTable('group_type')) {
            $connection->executeStatement('DROP TABLE group_type');
        }
        $connection->executeStatement('
            CREATE TABLE group_type (
                type TEXT NOT NULL PRIMARY KEY,
                bundle TEXT NOT NULL DEFAULT \'\',
                name TEXT NOT NULL DEFAULT \'\',
                langcode TEXT NOT NULL DEFAULT \'en\',
                _data TEXT NOT NULL DEFAULT \'{}\'
            )
        ');
        $connection->executeStatement('CREATE INDEX group_type_bundle ON group_type (bundle)');
    }
};
