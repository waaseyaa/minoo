<?php

declare(strict_types=1);

namespace App\Tests\Integration\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * The #923 rename migration: `group` -> `community_group`, shape-aware.
 *
 * Fixtures reproduce the two verified physical layouts of the same 15 logical
 * rows (orchestration-reports/minoo-waaseyaa/04-group-rename/schema.md):
 *
 *  - PROD (pre-extract): 6 legacy real columns on the base table plus an
 *    EMPTY Doctrine-DBAL-shaped `group__business` subtable and the legacy
 *    `group_type` table. The ledger falsely claims the 20260419 extract ran,
 *    so the migration must detect this shape itself (hasColumn guard).
 *  - LOCAL (post-extract): core-only base table, 15 populated rows in the
 *    hand-written `group__business` subtable, reshaped `group_type`.
 *
 * Both must converge on one canonical end state: `community_group` with core
 * columns + `_data` only, every business value folded into `_data`, the
 * bundle index recreated as `community_group_bundle`, and all engagement /
 * reference rows retargeted (`reaction`/`comment`/`featured_item` inside the
 * `_data` JSON blob; `follow`/`search_metadata`/`audit_event`/`embeddings`
 * via REAL columns — the F1/F2/F6 split from the adversarial review).
 */
#[CoversNothing]
final class CommunityGroupRenameMigrationTest extends TestCase
{
    private const MIGRATION = '/migrations/20260716_130000_rename_group_to_community_group.php';

    private const CANONICAL_COLUMNS = ['gid', 'uuid', 'type', 'name', 'langcode', '_data'];

    private Connection $connection;

    protected function setUp(): void
    {
        $db = DBALDatabase::createSqlite();
        $this->connection = $db->getConnection();
    }

    // ---------------------------------------------------------------------
    // PROD shape (pre-extract: 6 legacy columns + empty DBAL subtable)
    // ---------------------------------------------------------------------

    #[Test]
    public function up_on_prod_shape_folds_legacy_columns_into_data(): void
    {
        $this->createProdShape();

        $this->runUp();

        // Non-NULL legacy values folded, with column-affinity fidelity.
        self::assertSame(1, (int) $this->dataField(1, 'consent_public'));
        self::assertSame(0, (int) $this->dataField(1, 'consent_ai_training'));
        self::assertSame('integer', $this->dataType(1, 'consent_public'));
        self::assertSame(46.5, (float) $this->dataField(1, 'latitude'));
        self::assertSame(-81.9, (float) $this->dataField(1, 'longitude'));
        self::assertSame('real', $this->dataType(1, 'latitude'));
        self::assertSame('community', $this->dataField(1, 'coordinate_source'));
        self::assertSame('["https://fb.example/biz1"]', $this->dataField(1, 'social_posts'));

        // Consent values fold verbatim (not clamped to defaults).
        self::assertSame(0, (int) $this->dataField(3, 'consent_public'));
        self::assertSame(1, (int) $this->dataField(3, 'consent_ai_training'));
    }

    #[Test]
    public function up_on_prod_shape_omits_null_legacy_values_but_always_folds_consent(): void
    {
        $this->createProdShape();

        $this->runUp();

        // Row 2 has NULL geo/social columns: keys must be absent, not null.
        self::assertNull($this->dataType(2, 'latitude'));
        self::assertNull($this->dataType(2, 'longitude'));
        self::assertNull($this->dataType(2, 'coordinate_source'));
        self::assertNull($this->dataType(2, 'social_posts'));
        // Consent columns are NOT NULL with defaults: always folded.
        self::assertSame('integer', $this->dataType(2, 'consent_public'));
        self::assertSame('integer', $this->dataType(2, 'consent_ai_training'));
        // Row 3: latitude set but coordinate_source NULL (mixed).
        self::assertSame('real', $this->dataType(3, 'latitude'));
        self::assertNull($this->dataType(3, 'coordinate_source'));
    }

    #[Test]
    public function up_on_prod_shape_preserves_existing_data_keys_and_rows(): void
    {
        $this->createProdShape();

        $this->runUp();

        self::assertSame('biz-1', $this->dataField(1, 'slug'));
        self::assertSame('705-555-0001', $this->dataField(1, 'phone'));
        self::assertSame(1, (int) $this->dataField(1, 'status'));
        self::assertSame('biz-2', $this->dataField(2, 'slug'));
        self::assertSame(4, $this->rowCount('community_group'));
    }

    #[Test]
    public function up_renames_table_drops_siblings_and_recreates_bundle_index(): void
    {
        $this->createProdShape();

        $this->runUp();

        self::assertTrue($this->tableExists('community_group'));
        self::assertFalse($this->tableExists('group'));
        self::assertFalse($this->tableExists('group__business'));
        self::assertFalse($this->tableExists('community_group__business'));
        self::assertFalse($this->tableExists('group_type'));

        // Canonical shape: core columns + _data only; legacy columns dropped.
        self::assertSame(self::CANONICAL_COLUMNS, $this->columnNames('community_group'));

        // Index converged with what a fresh schema-sync would produce.
        self::assertTrue($this->indexExists('community_group_bundle', 'community_group'));
        self::assertFalse($this->indexExists('group_bundle'));
    }

    // ---------------------------------------------------------------------
    // LOCAL shape (post-extract: 15 populated subtable rows)
    // ---------------------------------------------------------------------

    #[Test]
    public function up_on_local_shape_folds_subtable_rows_into_base_data_per_gid(): void
    {
        $this->createLocalShape();

        $this->runUp();

        self::assertSame('biz-1', $this->dataField(1, 'slug'));
        self::assertSame('Business 1', $this->dataField(1, 'description'));
        self::assertSame('705-555-0001', $this->dataField(1, 'phone'));
        self::assertSame(7, (int) $this->dataField(1, 'community_id'));
        self::assertSame(1, (int) $this->dataField(1, 'consent_public'));
        self::assertSame(0, (int) $this->dataField(1, 'consent_ai_training'));
        self::assertSame(46.5, (float) $this->dataField(1, 'latitude'));
        self::assertSame('community', $this->dataField(1, 'coordinate_source'));

        // NULL subtable columns must not be folded (row 3 has NULL region/media_id).
        self::assertNull($this->dataType(3, 'region'));
        self::assertNull($this->dataType(3, 'media_id'));
        self::assertSame('biz-3', $this->dataField(3, 'slug'));

        // Pre-existing base _data keys survive the merge.
        self::assertSame(1, (int) $this->dataField(15, 'status'));
        self::assertNotNull($this->dataField(15, 'created_at'));
        self::assertSame('biz-15', $this->dataField(15, 'slug'));
    }

    #[Test]
    public function up_on_local_shape_reaches_same_canonical_end_state(): void
    {
        $this->createLocalShape();

        $this->runUp();

        self::assertTrue($this->tableExists('community_group'));
        self::assertFalse($this->tableExists('group'));
        self::assertFalse($this->tableExists('group__business'));
        self::assertFalse($this->tableExists('group_type'));
        self::assertSame(self::CANONICAL_COLUMNS, $this->columnNames('community_group'));
        self::assertSame(15, $this->rowCount('community_group'));
        self::assertTrue($this->indexExists('community_group_bundle', 'community_group'));
    }

    // ---------------------------------------------------------------------
    // Engagement / reference retargeting (json _data vs REAL column split)
    // ---------------------------------------------------------------------

    #[Test]
    public function up_retargets_group_references_in_both_storage_shapes(): void
    {
        $this->createLocalShape();
        $this->createReferenceTables();

        $this->runUp();

        // JSON `_data` refs: reaction / comment / featured_item.
        self::assertSame(
            'community_group',
            $this->jsonRef('reaction', 'target_type', 'rid', 1),
        );
        self::assertSame(
            'community_group',
            $this->jsonRef('comment', 'target_type', 'cid', 1),
        );
        self::assertSame(
            'community_group',
            $this->jsonRef('featured_item', 'entity_type', 'fid', 1),
        );
        // Non-group rows untouched.
        self::assertSame('post', $this->jsonRef('reaction', 'target_type', 'rid', 2));

        // REAL-column refs: a json_set() here would be a silent no-op (follow)
        // or a hard crash (search_metadata has no _data column at all).
        self::assertSame('community_group', $this->columnRef('follow', 'target_type', 'fid', 1));
        self::assertSame(
            'community_group',
            (string) $this->connection->fetchOne(
                'SELECT entity_type FROM search_metadata WHERE document_id = ?',
                ['group:1'],
            ),
        );
        self::assertSame('community_group', $this->columnRef('audit_event', 'entity_type_id', 'id', 1));
        self::assertSame(
            'community_group',
            (string) $this->connection->fetchOne(
                'SELECT entity_type FROM embeddings WHERE entity_id = ?',
                ['1'],
            ),
        );
        // Non-group audit rows untouched.
        self::assertSame('tm_backlog', $this->columnRef('audit_event', 'entity_type_id', 'id', 2));
    }

    #[Test]
    public function down_reverts_group_references_in_both_storage_shapes(): void
    {
        $this->createLocalShape();
        $this->createReferenceTables();

        $this->runUp();
        $this->runDown();

        self::assertSame('group', $this->jsonRef('reaction', 'target_type', 'rid', 1));
        self::assertSame('group', $this->jsonRef('comment', 'target_type', 'cid', 1));
        self::assertSame('group', $this->jsonRef('featured_item', 'entity_type', 'fid', 1));
        self::assertSame('post', $this->jsonRef('reaction', 'target_type', 'rid', 2));

        self::assertSame('group', $this->columnRef('follow', 'target_type', 'fid', 1));
        self::assertSame(
            'group',
            (string) $this->connection->fetchOne(
                'SELECT entity_type FROM search_metadata WHERE document_id = ?',
                ['group:1'],
            ),
        );
        self::assertSame('group', $this->columnRef('audit_event', 'entity_type_id', 'id', 1));
        self::assertSame(
            'group',
            (string) $this->connection->fetchOne(
                'SELECT entity_type FROM embeddings WHERE entity_id = ?',
                ['1'],
            ),
        );
    }

    // ---------------------------------------------------------------------
    // Guards: idempotency, half-state, unknown shape, version
    // ---------------------------------------------------------------------

    #[Test]
    public function up_no_ops_on_database_without_group_table(): void
    {
        // Already-migrated DBs and DBs that never had the table both land here.
        $this->runUp();

        self::assertFalse($this->tableExists('group'));
        self::assertFalse($this->tableExists('community_group'));
    }

    #[Test]
    public function up_rerun_after_migration_is_a_no_op(): void
    {
        $this->createProdShape();
        $this->runUp();

        $before = $this->connection->fetchAllAssociative(
            'SELECT gid, _data FROM community_group ORDER BY gid',
        );

        $this->runUp();

        self::assertSame(
            $before,
            $this->connection->fetchAllAssociative('SELECT gid, _data FROM community_group ORDER BY gid'),
        );
        self::assertTrue($this->indexExists('community_group_bundle', 'community_group'));
    }

    #[Test]
    public function up_throws_on_half_state_when_both_tables_exist(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE "group" ("gid" INTEGER PRIMARY KEY, "type" TEXT, "_data" TEXT)',
        );
        $this->connection->executeStatement(
            'CREATE TABLE community_group ("gid" INTEGER PRIMARY KEY, "type" TEXT, "_data" TEXT)',
        );

        $this->expectException(\RuntimeException::class);

        $this->runUp();
    }

    #[Test]
    public function up_throws_when_prod_shape_subtable_unexpectedly_has_rows(): void
    {
        $this->createProdShape();
        $this->connection->executeStatement(
            "INSERT INTO group__business (gid, slug) VALUES (1, 'unexpected')",
        );

        $this->expectException(\RuntimeException::class);

        $this->runUp();
    }

    #[Test]
    public function up_survives_preexisting_target_index_name(): void
    {
        // The post-down() reality: indexes survive RENAME TO under their old
        // names, so `community_group_bundle` can already exist when up() runs.
        // A bare CREATE INDEX crashes; IF NOT EXISTS is load-bearing.
        $this->createProdShape();
        $this->connection->executeStatement(
            'CREATE INDEX community_group_bundle ON "group" ("type")',
        );

        $this->runUp();

        self::assertTrue($this->indexExists('community_group_bundle', 'community_group'));
    }

    #[Test]
    public function migration_source_guards_sqlite_version_at_least_3_35(): void
    {
        $source_path = dirname(__DIR__, 4) . self::MIGRATION;
        self::assertFileExists($source_path);

        $source = (string) file_get_contents($source_path);
        self::assertStringContainsString('sqlite_version()', $source);
        self::assertStringContainsString('3.35', $source);
    }

    // ---------------------------------------------------------------------
    // down() and the down()->up() cycle
    // ---------------------------------------------------------------------

    #[Test]
    public function down_restores_group_table_with_bundle_index(): void
    {
        $this->createProdShape();
        $this->runUp();

        $this->runDown();

        self::assertTrue($this->tableExists('group'));
        self::assertFalse($this->tableExists('community_group'));
        self::assertTrue($this->indexExists('group_bundle', 'group'));
        self::assertFalse($this->indexExists('community_group_bundle'));

        // Rename-back only: the fold is NOT resurrected — old code reads the
        // folded `_data` shape correctly under sql-blob.
        self::assertSame(self::CANONICAL_COLUMNS, $this->columnNames('group'));
        self::assertSame(4, $this->rowCount('"group"'));
        self::assertSame(
            'biz-1',
            $this->connection->fetchOne(
                "SELECT json_extract(_data, '$.slug') FROM \"group\" WHERE gid = 1",
            ),
        );
    }

    #[Test]
    public function down_then_up_cycle_survives_index_recreation(): void
    {
        $this->createLocalShape();
        $this->runUp();
        $this->runDown();

        // Would crash on "index already exists" without the IF NOT EXISTS
        // semantics + down() dropping community_group_bundle.
        $this->runUp();

        self::assertTrue($this->tableExists('community_group'));
        self::assertFalse($this->tableExists('group'));
        self::assertTrue($this->indexExists('community_group_bundle', 'community_group'));
        self::assertFalse($this->indexExists('group_bundle'));
        self::assertSame(15, $this->rowCount('community_group'));
    }

    // ---------------------------------------------------------------------
    // Fixtures — DDL is verbatim from schema.md (prod snapshot / local copy)
    // ---------------------------------------------------------------------

    /**
     * PROD shape: 6 legacy real columns on the base table, an EMPTY
     * DBAL-shaped `group__business`, and the legacy `group_type` table.
     */
    private function createProdShape(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE "group" ("gid" INTEGER PRIMARY KEY AUTOINCREMENT,'
            . ' "uuid" TEXT NOT NULL DEFAULT \'\', "type" TEXT NOT NULL DEFAULT \'\','
            . ' "name" TEXT NOT NULL DEFAULT \'\', "langcode" TEXT NOT NULL DEFAULT \'en\','
            . ' "_data" TEXT NOT NULL DEFAULT \'{}\','
            . ' social_posts TEXT, consent_public INTEGER NOT NULL DEFAULT 1,'
            . ' consent_ai_training INTEGER NOT NULL DEFAULT 0, latitude REAL,'
            . ' longitude REAL, coordinate_source TEXT,'
            . ' CONSTRAINT "group_uuid" UNIQUE ("uuid"))',
        );
        $this->connection->executeStatement('CREATE INDEX "group_bundle" ON "group" ("type")');

        // Doctrine-DBAL fingerprint: CLOB/BOOLEAN/DOUBLE PRECISION, named FK.
        $this->connection->executeStatement(
            'CREATE TABLE group__business (gid INTEGER NOT NULL, slug CLOB DEFAULT NULL,'
            . ' description CLOB DEFAULT NULL, url CLOB DEFAULT NULL, region CLOB DEFAULT NULL,'
            . ' community_id CLOB DEFAULT NULL, phone CLOB DEFAULT NULL, email CLOB DEFAULT NULL,'
            . ' address CLOB DEFAULT NULL, booking_url CLOB DEFAULT NULL, media_id CLOB DEFAULT NULL,'
            . ' copyright_status CLOB DEFAULT \'unknown\', consent_public BOOLEAN DEFAULT 1,'
            . ' consent_ai_training BOOLEAN DEFAULT 0, source CLOB DEFAULT NULL,'
            . ' verified_at CLOB DEFAULT NULL, social_posts CLOB DEFAULT NULL,'
            . ' latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL,'
            . ' coordinate_source CLOB DEFAULT NULL, PRIMARY KEY (gid),'
            . ' CONSTRAINT group__business_fk FOREIGN KEY (gid) REFERENCES "group" (gid)'
            . ' ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)',
        );

        // Legacy (pre-extract) group_type shape.
        $this->connection->executeStatement(
            'CREATE TABLE "group_type" ("type" TEXT NOT NULL, "bundle" TEXT NOT NULL DEFAULT \'\','
            . ' "name" TEXT NOT NULL DEFAULT \'\', "langcode" TEXT NOT NULL DEFAULT \'en\','
            . ' "_data" TEXT NOT NULL DEFAULT \'{}\', PRIMARY KEY ("type"))',
        );
        $this->connection->executeStatement(
            'CREATE INDEX "group_type_bundle" ON "group_type" ("bundle")',
        );

        // 4 synthetic rows covering the observed `_data` key variants and
        // NULL/non-NULL legacy column combinations.
        $this->insertProdRow(1, [
            'slug' => 'biz-1', 'description' => 'Business 1', 'phone' => '705-555-0001',
            'email' => 'one@example.test', 'address' => '1 Main St', 'url' => 'https://biz1.test',
            'booking_url' => 'https://biz1.test/book', 'source' => 'community_submission',
            'verified_at' => '2026-01-01', 'community_id' => 7,
            'created_at' => '2026-01-01T00:00:00+00:00', 'updated_at' => '2026-01-02T00:00:00+00:00',
            'status' => 1, 'copyright_status' => 'unknown',
        ], social: '["https://fb.example/biz1"]', consentPublic: 1, consentAi: 0, lat: 46.5, lon: -81.9, coordSource: 'community');
        $this->insertProdRow(2, [
            'slug' => 'biz-2', 'description' => 'Business 2', 'address' => '2 Main St',
            'url' => 'https://biz2.test', 'source' => 'community_submission',
            'verified_at' => '2026-01-01', 'community_id' => 7,
            'created_at' => '2026-01-01T00:00:00+00:00', 'updated_at' => '2026-01-02T00:00:00+00:00',
            'status' => 1, 'copyright_status' => 'unknown',
        ], social: null, consentPublic: 1, consentAi: 0, lat: null, lon: null, coordSource: null);
        $this->insertProdRow(3, [
            'slug' => 'biz-3', 'description' => 'Business 3', 'source' => 'community_submission',
            'verified_at' => '2026-01-01', 'community_id' => 8,
            'created_at' => '2026-01-01T00:00:00+00:00', 'updated_at' => '2026-01-02T00:00:00+00:00',
            'status' => 1, 'copyright_status' => 'unknown',
        ], social: null, consentPublic: 0, consentAi: 1, lat: 47.25, lon: -80.25, coordSource: null);
        $this->insertProdRow(4, [
            'slug' => 'biz-4', 'description' => 'Business 4', 'url' => 'https://biz4.test',
            'source' => 'community_submission',
            'created_at' => '2026-01-01T00:00:00+00:00', 'updated_at' => '2026-01-02T00:00:00+00:00',
            'status' => 0, 'copyright_status' => 'unknown',
        ], social: null, consentPublic: 1, consentAi: 1, lat: null, lon: null, coordSource: null);
    }

    /**
     * LOCAL shape: core-only base table, 15 populated `group__business` rows
     * (hand-written DDL from the 20260419 extract), reshaped `group_type`.
     */
    private function createLocalShape(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE "group" ("gid" INTEGER PRIMARY KEY AUTOINCREMENT,'
            . ' "uuid" TEXT NOT NULL DEFAULT \'\', "type" TEXT NOT NULL DEFAULT \'\','
            . ' "name" TEXT NOT NULL DEFAULT \'\', "langcode" TEXT NOT NULL DEFAULT \'en\','
            . ' "_data" TEXT NOT NULL DEFAULT \'{}\', CONSTRAINT "group_uuid" UNIQUE ("uuid"))',
        );
        $this->connection->executeStatement('CREATE INDEX "group_bundle" ON "group" ("type")');

        $this->connection->executeStatement(
            'CREATE TABLE group__business (gid INTEGER PRIMARY KEY, slug TEXT, description TEXT,'
            . ' url TEXT, region TEXT, community_id INTEGER, phone TEXT, email TEXT, address TEXT,'
            . ' booking_url TEXT, media_id INTEGER, copyright_status TEXT,'
            . ' consent_public INTEGER NOT NULL DEFAULT 1, consent_ai_training INTEGER NOT NULL DEFAULT 0,'
            . ' social_posts TEXT, source TEXT, verified_at TEXT, coordinate_source TEXT,'
            . ' latitude REAL, longitude REAL,'
            . ' FOREIGN KEY (gid) REFERENCES "group" (gid) ON DELETE CASCADE)',
        );

        // Reshaped (post-extract) group_type.
        $this->connection->executeStatement(
            'CREATE TABLE group_type (id TEXT NOT NULL PRIMARY KEY,'
            . ' label TEXT NOT NULL DEFAULT \'\', description TEXT,'
            . ' _data TEXT NOT NULL DEFAULT \'{}\')',
        );

        for ($gid = 1; $gid <= 15; $gid++) {
            $this->connection->executeStatement(
                'INSERT INTO "group" (gid, uuid, type, name, langcode, _data) VALUES (?, ?, ?, ?, ?, ?)',
                [
                    $gid,
                    'uuid-' . $gid,
                    'business',
                    'Business ' . $gid,
                    'en',
                    json_encode([
                        'created_at' => '2026-01-01T00:00:00+00:00',
                        'updated_at' => '2026-01-02T00:00:00+00:00',
                        'status' => 1,
                    ], JSON_THROW_ON_ERROR),
                ],
            );
            $this->connection->executeStatement(
                'INSERT INTO group__business (gid, slug, description, url, region, community_id,'
                . ' phone, email, address, booking_url, media_id, copyright_status,'
                . ' consent_public, consent_ai_training, social_posts, source, verified_at,'
                . ' coordinate_source, latitude, longitude)'
                . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $gid,
                    'biz-' . $gid,
                    'Business ' . $gid,
                    'https://biz' . $gid . '.test',
                    $gid === 3 ? null : 'manitoulin',
                    7,
                    '705-555-000' . ($gid % 10),
                    'biz' . $gid . '@example.test',
                    $gid . ' Main St',
                    null,
                    $gid === 3 ? null : 100 + $gid,
                    'unknown',
                    1,
                    0,
                    null,
                    'community_submission',
                    '2026-01-01',
                    'community',
                    46.5,
                    -81.9,
                ],
            );
        }
    }

    /**
     * Every §4.4 retargeting site, seeded with one synthetic 'group' ref each
     * (plus non-group controls). DDL verbatim from the prod snapshot.
     */
    private function createReferenceTables(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE reaction (rid INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,'
            . " uuid CLOB DEFAULT '' NOT NULL, bundle CLOB DEFAULT '' NOT NULL,"
            . " reaction_type CLOB DEFAULT '' NOT NULL, langcode CLOB DEFAULT 'en' NOT NULL,"
            . " _data CLOB DEFAULT '{}' NOT NULL)",
        );
        $this->connection->executeStatement(
            "INSERT INTO reaction (rid, reaction_type, _data) VALUES"
            . " (1, 'like', '{\"target_type\":\"group\",\"target_id\":1,\"user_id\":1}'),"
            . " (2, 'like', '{\"target_type\":\"post\",\"target_id\":9,\"user_id\":1}')",
        );

        $this->connection->executeStatement(
            'CREATE TABLE comment (cid INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,'
            . " uuid CLOB DEFAULT '' NOT NULL, bundle CLOB DEFAULT '' NOT NULL,"
            . " body CLOB DEFAULT '' NOT NULL, langcode CLOB DEFAULT 'en' NOT NULL,"
            . " _data CLOB DEFAULT '{}' NOT NULL)",
        );
        $this->connection->executeStatement(
            'INSERT INTO comment (cid, body, _data) VALUES'
            . " (1, 'hello', '{\"target_type\":\"group\",\"target_id\":1,\"user_id\":1}')",
        );

        $this->connection->executeStatement(
            'CREATE TABLE "featured_item" ("fid" INTEGER PRIMARY KEY AUTOINCREMENT,'
            . ' "uuid" TEXT NOT NULL DEFAULT \'\', "bundle" TEXT NOT NULL DEFAULT \'\','
            . ' "headline" TEXT NOT NULL DEFAULT \'\', "langcode" TEXT NOT NULL DEFAULT \'en\','
            . ' "_data" TEXT NOT NULL DEFAULT \'{}\', CONSTRAINT "featured_item_uuid" UNIQUE ("uuid"))',
        );
        $this->connection->executeStatement(
            'INSERT INTO featured_item (fid, headline, _data) VALUES'
            . " (1, 'Featured biz', '{\"entity_type\":\"group\",\"entity_id\":1}')",
        );

        // follow.target_type is a REAL column (vendor label key), not _data.
        $this->connection->executeStatement(
            'CREATE TABLE follow (fid INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,'
            . " uuid CLOB DEFAULT '' NOT NULL, bundle CLOB DEFAULT '' NOT NULL,"
            . " target_type CLOB DEFAULT '' NOT NULL, langcode CLOB DEFAULT 'en' NOT NULL,"
            . " _data CLOB DEFAULT '{}' NOT NULL)",
        );
        $this->connection->executeStatement(
            "INSERT INTO follow (fid, target_type, _data) VALUES (1, 'group', '{\"target_id\":1}')",
        );

        // search_metadata has NO _data column at all.
        $this->connection->executeStatement(
            'CREATE TABLE search_metadata (document_id TEXT PRIMARY KEY, entity_type TEXT NOT NULL,'
            . " content_type TEXT NOT NULL DEFAULT '', source_name TEXT NOT NULL DEFAULT '',"
            . " quality_score INTEGER NOT NULL DEFAULT 0, topics TEXT NOT NULL DEFAULT '[]',"
            . " url TEXT NOT NULL DEFAULT '', og_image TEXT NOT NULL DEFAULT '',"
            . ' created_at TEXT NOT NULL, schema_version TEXT NOT NULL)',
        );
        $this->connection->executeStatement(
            'INSERT INTO search_metadata (document_id, entity_type, created_at, schema_version)'
            . " VALUES ('group:1', 'group', '2026-07-01', '1')",
        );

        $this->connection->executeStatement(
            'CREATE TABLE audit_event (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,'
            . " uuid VARCHAR(128) NOT NULL DEFAULT '', event_kind VARCHAR(64) NOT NULL DEFAULT '',"
            . ' account_uid INTEGER NOT NULL DEFAULT 0, actor_uid INTEGER NULL,'
            . " entity_type_id VARCHAR(128) NOT NULL DEFAULT '', entity_uuid VARCHAR(128) NOT NULL DEFAULT '',"
            . " subject_uri VARCHAR(512) NOT NULL DEFAULT '', outcome VARCHAR(16) NOT NULL DEFAULT 'allowed',"
            . " severity VARCHAR(16) NOT NULL DEFAULT 'info', attributes TEXT NOT NULL DEFAULT '{}',"
            . ' created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)',
        );
        $this->connection->executeStatement(
            'INSERT INTO audit_event (id, event_kind, entity_type_id) VALUES'
            . " (1, 'entity.view', 'group'), (2, 'entity.view', 'tm_backlog')",
        );

        $this->connection->executeStatement(
            'CREATE TABLE embeddings (entity_type TEXT NOT NULL, entity_id TEXT NOT NULL,'
            . ' vector TEXT NOT NULL, updated_at INTEGER NOT NULL, PRIMARY KEY(entity_type, entity_id))',
        );
        $this->connection->executeStatement(
            "INSERT INTO embeddings (entity_type, entity_id, vector, updated_at) VALUES ('group', '1', '[]', 0)",
        );
    }

    /**
     * @param array<string, int|string> $data
     */
    private function insertProdRow(
        int $gid,
        array $data,
        ?string $social,
        int $consentPublic,
        int $consentAi,
        ?float $lat,
        ?float $lon,
        ?string $coordSource,
    ): void {
        $this->connection->executeStatement(
            'INSERT INTO "group" (gid, uuid, type, name, langcode, _data, social_posts,'
            . ' consent_public, consent_ai_training, latitude, longitude, coordinate_source)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $gid,
                'uuid-' . $gid,
                'business',
                'Business ' . $gid,
                'en',
                json_encode($data, JSON_THROW_ON_ERROR),
                $social,
                $consentPublic,
                $consentAi,
                $lat,
                $lon,
                $coordSource,
            ],
        );
    }

    // ---------------------------------------------------------------------
    // Harness + probes
    // ---------------------------------------------------------------------

    private function runUp(): void
    {
        $migration = require dirname(__DIR__, 4) . self::MIGRATION;
        $migration->up(new SchemaBuilder($this->connection));
    }

    private function runDown(): void
    {
        $migration = require dirname(__DIR__, 4) . self::MIGRATION;
        $migration->down(new SchemaBuilder($this->connection));
    }

    private function tableExists(string $table): bool
    {
        return (bool) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?",
            [$table],
        );
    }

    private function indexExists(string $index, ?string $onTable = null): bool
    {
        $sql = "SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND name = ?";
        $params = [$index];
        if ($onTable !== null) {
            $sql .= ' AND tbl_name = ?';
            $params[] = $onTable;
        }

        return (bool) $this->connection->fetchOne($sql, $params);
    }

    /**
     * @return list<string>
     */
    private function columnNames(string $table): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT name FROM pragma_table_info(?) ORDER BY cid',
            [$table],
        );

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }

    private function rowCount(string $table): int
    {
        return (int) $this->connection->fetchOne("SELECT COUNT(*) FROM {$table}");
    }

    /**
     * json_extract of a key from the migrated community_group (or renamed-back
     * group) row — whichever of the two tables currently exists.
     */
    private function dataField(int $gid, string $key): mixed
    {
        $table = $this->tableExists('community_group') ? 'community_group' : '"group"';
        $value = $this->connection->fetchOne(
            "SELECT json_extract(_data, '$.' || ?) FROM {$table} WHERE gid = ?",
            [$key, $gid],
        );

        return $value === false ? null : $value;
    }

    /**
     * SQLite json_type of a key: 'integer'/'real'/'text'/… or NULL when the
     * key is absent from the blob (which is distinct from a null value).
     */
    private function dataType(int $gid, string $key): ?string
    {
        $table = $this->tableExists('community_group') ? 'community_group' : '"group"';
        $value = $this->connection->fetchOne(
            "SELECT json_type(_data, '$.' || ?) FROM {$table} WHERE gid = ?",
            [$key, $gid],
        );

        return $value === false || $value === null ? null : (string) $value;
    }

    private function jsonRef(string $table, string $key, string $idColumn, int $id): ?string
    {
        $value = $this->connection->fetchOne(
            "SELECT json_extract(_data, '$.{$key}') FROM {$table} WHERE {$idColumn} = ?",
            [$id],
        );

        return $value === false || $value === null ? null : (string) $value;
    }

    private function columnRef(string $table, string $column, string $idColumn, int $id): ?string
    {
        $value = $this->connection->fetchOne(
            "SELECT {$column} FROM {$table} WHERE {$idColumn} = ?",
            [$id],
        );

        return $value === false || $value === null ? null : (string) $value;
    }
}
