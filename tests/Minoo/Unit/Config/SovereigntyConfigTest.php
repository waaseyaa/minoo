<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Sovereignty\SovereigntyConfig;
use Waaseyaa\Foundation\Sovereignty\SovereigntyProfile;

final class SovereigntyConfigTest extends TestCase
{
    #[Test]
    public function minoo_config_defaults_to_northops_profile(): void
    {
        $appConfig = require __DIR__ . '/../../../../config/waaseyaa.php';
        $sovereignty = SovereigntyConfig::fromArray($appConfig);
        $this->assertSame(SovereigntyProfile::NorthOps, $sovereignty->getProfile());
    }

    #[Test]
    public function sovereignty_profile_env_var_overrides_default(): void
    {
        putenv('WAASEYAA_SOVEREIGNTY_PROFILE=local');

        $appConfig = require __DIR__ . '/../../../../config/waaseyaa.php';
        $sovereignty = SovereigntyConfig::fromArray($appConfig);
        $this->assertSame(SovereigntyProfile::Local, $sovereignty->getProfile());

        putenv('WAASEYAA_SOVEREIGNTY_PROFILE=');
    }
}
