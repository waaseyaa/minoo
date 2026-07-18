<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PHPUnit\Event\TestSuite\Started;
use PHPUnit\Event\TestSuite\StartedSubscriber;
use PHPUnit\Event\TestSuite\TestSuiteForTestClass;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Clears framework static state between test classes so each kernel boot
 * starts clean (waaseyaa alpha.267 upgrade, #920).
 *
 * SsrServiceProvider caches the Twig Environment in a static and exposes it
 * through a container `Environment::class` binding. Statics survive across
 * HttpKernel instances within one PHPUnit process, so after any test class
 * RENDERS (locking the environment's extension set), the next test class's
 * kernel boot crashes: SeoServiceProvider::boot() runs before SSR boots,
 * resolves the previous kernel's already-initialized environment via the
 * binding, and its addExtension() throws
 * "Unable to register extension ... as extensions have already been initialized."
 * Resetting via the public setTwigEnvironment() seam (framework #1604) before
 * each class restores the single-kernel-per-process semantics production has.
 */
final class ResetFrameworkStaticsExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $facade->registerSubscriber(new class () implements StartedSubscriber {
            public function notify(Started $event): void
            {
                // Class suites only: a Started event also fires for each
                // data-provider sub-suite MID-class, and resetting there would
                // strip the booted kernel's environment out from under its own
                // renders (SsrPageHandler reads the static at render time).
                if ($event->testSuite() instanceof TestSuiteForTestClass) {
                    SsrServiceProvider::setTwigEnvironment(null);
                }
            }
        });
    }
}
