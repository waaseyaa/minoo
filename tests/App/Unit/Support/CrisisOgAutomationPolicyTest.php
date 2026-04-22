<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Support\CrisisOgAutomationPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CrisisOgAutomationPolicyTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('MINOO_CRISIS_OG_AUTO');
        parent::tearDown();
    }

    #[Test]
    public function global_opt_in_false_when_unset(): void
    {
        putenv('MINOO_CRISIS_OG_AUTO');
        self::assertFalse(CrisisOgAutomationPolicy::globalOptIn());
    }

    #[Test]
    public function global_opt_in_true_when_one(): void
    {
        putenv('MINOO_CRISIS_OG_AUTO=1');
        self::assertTrue(CrisisOgAutomationPolicy::globalOptIn());
    }

    #[Test]
    public function managed_path_detection(): void
    {
        self::assertTrue(CrisisOgAutomationPolicy::isManagedGeneratedWebPath('/og/crisis/sagamok-spanish-river-flood.png'));
        self::assertFalse(CrisisOgAutomationPolicy::isManagedGeneratedWebPath('/img/custom.png'));
        self::assertFalse(CrisisOgAutomationPolicy::isManagedGeneratedWebPath('/og/crisis/../etc/passwd'));
        self::assertFalse(CrisisOgAutomationPolicy::isManagedGeneratedWebPath(''));
    }

    #[Test]
    public function build_batch_requires_opt_in(): void
    {
        putenv('MINOO_CRISIS_OG_AUTO');
        $registry = ['og_generate' => true, 'og_generate_mode' => 'both'];
        $incident = ['og_image_path' => '/og/crisis/foo.png'];
        self::assertSame('opt_in_off', CrisisOgAutomationPolicy::buildBatchIneligibilityReason($registry, $incident));
    }

    #[Test]
    public function build_batch_eligible_when_opt_in_and_flags(): void
    {
        putenv('MINOO_CRISIS_OG_AUTO=1');
        $registry = ['og_generate' => true, 'og_generate_mode' => 'both'];
        $incident = ['og_image_path' => '/og/crisis/foo.png'];
        self::assertNull(CrisisOgAutomationPolicy::buildBatchIneligibilityReason($registry, $incident));
    }

    #[Test]
    public function build_batch_skips_fallback_mode(): void
    {
        putenv('MINOO_CRISIS_OG_AUTO=1');
        $registry = ['og_generate' => true, 'og_generate_mode' => 'fallback'];
        $incident = ['og_image_path' => '/og/crisis/foo.png'];
        self::assertSame(
            'og_generate_mode_fallback',
            CrisisOgAutomationPolicy::buildBatchIneligibilityReason($registry, $incident),
        );
    }

    #[Test]
    public function build_batch_requires_og_generate(): void
    {
        putenv('MINOO_CRISIS_OG_AUTO=1');
        $registry = ['og_generate' => false, 'og_generate_mode' => 'both'];
        $incident = ['og_image_path' => '/og/crisis/foo.png'];
        self::assertSame(
            'og_generate_false',
            CrisisOgAutomationPolicy::buildBatchIneligibilityReason($registry, $incident),
        );
    }

    #[Test]
    public function http_dynamic_blocks_build_only_mode(): void
    {
        $registry = ['og_generate' => true, 'og_generate_mode' => 'build'];
        $incident = ['og_image_path' => '/og/crisis/foo.png'];
        self::assertSame(
            'og_generate_mode_build_only',
            CrisisOgAutomationPolicy::httpDynamicWhenMissingIneligibilityReason($registry, $incident),
        );
    }

    #[Test]
    public function http_dynamic_allows_without_global_opt_in(): void
    {
        putenv('MINOO_CRISIS_OG_AUTO');
        $registry = ['og_generate' => true, 'og_generate_mode' => 'both'];
        $incident = ['og_image_path' => '/og/crisis/foo.png'];
        self::assertNull(CrisisOgAutomationPolicy::httpDynamicWhenMissingIneligibilityReason($registry, $incident));
    }

    #[Test]
    public function background_requires_opt_in(): void
    {
        putenv('MINOO_CRISIS_OG_AUTO');
        $registry = ['og_generate' => true, 'og_generate_mode' => 'both'];
        $incident = ['og_image_path' => '/og/crisis/foo.png'];
        self::assertSame(
            'opt_in_off',
            CrisisOgAutomationPolicy::backgroundWriteIneligibilityReason($registry, $incident),
        );
    }

    #[Test]
    public function manual_regenerate_ignores_opt_in(): void
    {
        putenv('MINOO_CRISIS_OG_AUTO');
        $incident = ['og_image_path' => '/og/crisis/foo.png'];
        self::assertNull(CrisisOgAutomationPolicy::manualRegenerateIneligibilityReason($incident));
    }
}
