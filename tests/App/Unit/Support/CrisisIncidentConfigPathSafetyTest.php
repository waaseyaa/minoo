<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Support\CrisisIncidentConfigPathSafety;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CrisisIncidentConfigPathSafetyTest extends TestCase
{
    private string $fixtureRoot;

    protected function setUp(): void
    {
        $this->fixtureRoot = sys_get_temp_dir() . '/minoo_crisis_path_' . bin2hex(random_bytes(4));
        mkdir($this->fixtureRoot . '/config/crisis', 0755, true);
        file_put_contents(
            $this->fixtureRoot . '/config/crisis/allowed.php',
            "<?php\nreturn ['ok' => true];\n",
        );
        file_put_contents(
            $this->fixtureRoot . '/outside.php',
            "<?php\nreturn ['bad' => true];\n",
        );
    }

    protected function tearDown(): void
    {
        $files = [
            $this->fixtureRoot . '/config/crisis/allowed.php',
            $this->fixtureRoot . '/outside.php',
        ];
        foreach ($files as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
        if (is_dir($this->fixtureRoot . '/config/crisis')) {
            rmdir($this->fixtureRoot . '/config/crisis');
        }
        if (is_dir($this->fixtureRoot . '/config')) {
            rmdir($this->fixtureRoot . '/config');
        }
        if (is_dir($this->fixtureRoot)) {
            rmdir($this->fixtureRoot);
        }
        parent::tearDown();
    }

    #[Test]
    public function allows_file_under_config_crisis_relative(): void
    {
        $p = CrisisIncidentConfigPathSafety::validatedAbsoluteConfigPath(
            $this->fixtureRoot,
            'config/crisis/allowed.php',
        );
        self::assertNotNull($p);
        self::assertStringContainsString('allowed.php', $p);
    }

    #[Test]
    public function rejects_traversal_outside_crisis_dir(): void
    {
        self::assertNull(CrisisIncidentConfigPathSafety::validatedAbsoluteConfigPath(
            $this->fixtureRoot,
            'config/crisis/../outside.php',
        ));
    }

    #[Test]
    public function rejects_absolute_path_outside_crisis_dir(): void
    {
        $outside = $this->fixtureRoot . DIRECTORY_SEPARATOR . 'outside.php';
        self::assertNull(CrisisIncidentConfigPathSafety::validatedAbsoluteConfigPath(
            $this->fixtureRoot,
            $outside,
        ));
    }

    #[Test]
    public function rejects_null_byte(): void
    {
        self::assertNull(CrisisIncidentConfigPathSafety::validatedAbsoluteConfigPath(
            $this->fixtureRoot,
            "config/crisis/allowed.php\0/../outside.php",
        ));
    }

    #[Test]
    public function rejects_missing_file(): void
    {
        self::assertNull(CrisisIncidentConfigPathSafety::validatedAbsoluteConfigPath(
            $this->fixtureRoot,
            'config/crisis/does-not-exist.php',
        ));
    }
}
