<?php

declare(strict_types=1);

namespace App\Tests\Unit\Bin;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests the bin/export-communities CLI governance gates.
 *
 * These tests invoke the script as a subprocess and inspect exit codes
 * and stderr/stdout output. They do NOT boot the kernel — the governance
 * checks run before kernel boot, so no database is needed.
 */
#[CoversNothing]
final class ExportCommunitiesTest extends TestCase
{
    private string $script;

    protected function setUp(): void
    {
        $this->script = dirname(__DIR__, 4) . '/bin/export-communities';
    }

    #[Test]
    public function without_confirm_exits_with_governance_notice(): void
    {
        $output = [];
        $exitCode = 0;

        // escapeshellarg prevents injection — safe usage of exec
        exec(sprintf('php %s 2>&1', escapeshellarg($this->script)), $output, $exitCode);

        $text = implode("\n", $output);

        self::assertSame(1, $exitCode, 'Should exit with code 1 when --confirm is missing');
        self::assertStringContainsString('GOVERNANCE NOTICE', $text);
        self::assertStringContainsString('--confirm', $text);
        self::assertStringContainsString('--dry-run', $text);
    }

    #[Test]
    public function dry_run_shows_preview_without_exporting(): void
    {
        $output = [];
        $exitCode = 0;

        // escapeshellarg prevents injection — safe usage of exec
        exec(sprintf('php %s --dry-run 2>&1', escapeshellarg($this->script)), $output, $exitCode);

        $text = implode("\n", $output);

        // Skip when kernel can't boot (e.g. no database in CI)
        if ($exitCode === 255) {
            self::markTestSkipped('Kernel boot failed (no database): ' . $text);
        }

        self::assertSame(0, $exitCode, 'Dry run should exit with code 0. Output: ' . $text);
        self::assertStringContainsString('DRY RUN', $text);
        self::assertStringContainsString('Records:', $text);
        self::assertStringContainsString('Fields:', $text);
        // Verify key field names appear in the preview
        self::assertStringContainsString('inac_id', $text);
        self::assertStringContainsString('name', $text);
    }

    #[Test]
    public function confirm_flag_proceeds_past_governance_gate(): void
    {
        $output = [];
        $exitCode = 0;

        // With --confirm the script will try to boot the kernel and load
        // entities. Without a database it will fail, but it should NOT
        // show the governance notice — proving the gate was passed.
        exec(sprintf('php %s --confirm 2>&1', escapeshellarg($this->script)), $output, $exitCode);

        $text = implode("\n", $output);

        self::assertStringNotContainsString('GOVERNANCE NOTICE', $text, 'Governance gate should be bypassed with --confirm');
    }
}
