<?php

declare(strict_types=1);

namespace App\Tests\Unit\Config;

use Anokii\Config\DistributionConfig;
use Anokii\Config\TenancyMode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Asserts minoo's config/anokii.yaml and the DistributionConfig wiring:
 * sovereign tenancy, the language module present but off, the flag flips true
 * when enabled, and an absent file matches the fnpi-waaseyaa sovereign default.
 */
final class AnokiiDistributionConfigTest extends TestCase
{
    #[Test]
    public function minoo_anokii_config_loads_with_sovereign_tenancy(): void
    {
        $config = DistributionConfig::fromFile(__DIR__ . '/../../../../config/anokii.yaml');

        $this->assertSame(TenancyMode::Sovereign, $config->tenancyMode());
    }

    #[Test]
    public function language_module_is_off_by_default(): void
    {
        $config = DistributionConfig::fromFile(__DIR__ . '/../../../../config/anokii.yaml');

        $this->assertFalse($config->moduleEnabled('language'));
    }

    #[Test]
    public function enabling_the_language_flag_flips_module_enabled_true(): void
    {
        $config = DistributionConfig::fromArray([
            'tenancy_mode' => 'sovereign',
            'modules' => ['enabled' => ['language'], 'preview' => []],
        ]);

        $this->assertTrue($config->moduleEnabled('language'));
    }

    #[Test]
    public function absent_config_file_matches_fnpi_sovereign_default(): void
    {
        $config = DistributionConfig::fromFile(__DIR__ . '/does-not-exist.yaml');

        $this->assertSame(TenancyMode::Sovereign, $config->tenancyMode());
        $this->assertFalse($config->moduleEnabled('language'));
    }
}
