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
 * The OPD licence + dialect backfill (#914). The single property that matters
 * for licence hygiene: the CC BY-NC-SA 3.0 ShareAlike obligation must attach to
 * the Ojibwe People's Dictionary rows and ONLY those rows. Steven Bennett's
 * Sagamok community corpus (attribution_source = 'corpus') must come through the
 * migration untouched, so its licence stays empty and its dialect stays its own.
 */
#[CoversNothing]
final class OpdLicenseBackfillMigrationTest extends TestCase
{
    private const MIGRATION = '/migrations/20260625_130000_mark_opd_license_and_dialect.php';

    private Connection $connection;

    protected function setUp(): void
    {
        $db = DBALDatabase::createSqlite();
        $this->connection = $db->getConnection();

        // Minimal content-entity table: only `_data` is read by the migration.
        $this->connection->executeStatement(
            'CREATE TABLE dictionary_entry (
                deid INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid CLOB, bundle CLOB, word CLOB, langcode CLOB, _data CLOB
            )',
        );

        $this->insert('makwa', [
            'attribution_source' => "Ojibwe People's Dictionary, University of Minnesota",
            'attribution_url' => 'https://ojibwe.lib.umn.edu/main-entry/makwa-na',
            'language_code' => 'oj',
        ]);
        // OPD row identified only by its URL fingerprint (source label absent).
        $this->insert('nibi', [
            'attribution_source' => '',
            'attribution_url' => 'https://ojibwe.lib.umn.edu/main-entry/nibi-ni',
            'language_code' => 'oj',
        ]);
        // Sagamok community corpus row: must NOT be licensed or relabelled.
        $this->insert('giizhig', [
            'attribution_source' => 'corpus',
            'attribution_url' => 'https://www.facebook.com/groups/sagamok',
            'language_code' => 'oj',
        ]);
    }

    #[Test]
    public function up_licenses_and_relabels_only_the_opd_rows(): void
    {
        $this->runUp();

        self::assertSame('CC BY-NC-SA 3.0', $this->license('makwa'));
        self::assertSame('oj-sw', $this->languageCode('makwa'));
        self::assertSame('CC BY-NC-SA 3.0', $this->license('nibi'));
        self::assertSame('oj-sw', $this->languageCode('nibi'));
    }

    #[Test]
    public function up_never_touches_the_sagamok_corpus(): void
    {
        $this->runUp();

        self::assertNull($this->license('giizhig'), 'Corpus row must carry no licence.');
        self::assertSame('oj', $this->languageCode('giizhig'), 'Corpus dialect must be unchanged.');
    }

    #[Test]
    public function down_restores_the_original_plain_oj_rows(): void
    {
        $this->runUp();
        $this->runDown();

        self::assertNull($this->license('makwa'));
        self::assertSame('oj', $this->languageCode('makwa'));
        // The corpus row was never modified either way.
        self::assertNull($this->license('giizhig'));
        self::assertSame('oj', $this->languageCode('giizhig'));
    }

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

    /**
     * @param array<string, string> $data
     */
    private function insert(string $word, array $data): void
    {
        $this->connection->insert('dictionary_entry', [
            'word' => $word,
            '_data' => json_encode($data, JSON_THROW_ON_ERROR),
        ]);
    }

    private function license(string $word): ?string
    {
        return $this->field($word, 'license');
    }

    private function languageCode(string $word): ?string
    {
        return $this->field($word, 'language_code');
    }

    private function field(string $word, string $key): ?string
    {
        $value = $this->connection->fetchOne(
            "SELECT json_extract(_data, '$.' || ?) FROM dictionary_entry WHERE word = ?",
            [$key, $word],
        );

        return $value === false ? null : $value;
    }
}
