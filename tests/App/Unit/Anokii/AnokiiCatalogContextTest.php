<?php

declare(strict_types=1);

namespace App\Tests\Unit\Anokii;

use Anokii\Config\DistributionConfig;
use App\Http\View\AnokiiShellContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;

/**
 * AnokiiShellContext::catalog() builds the /admin/anokii dashboard from the
 * package AdminModules catalog (#886): the home stays live, disabled modules
 * resolve to product-preview cards, and a DistributionConfig flag flips a module
 * to a live tool. Minoo keeps its own account model (AccountInterface).
 */
final class AnokiiCatalogContextTest extends TestCase
{
    #[Test]
    public function dashboard_builds_the_catalog_with_no_live_tools_when_nothing_is_enabled(): void
    {
        $ctx = AnokiiShellContext::catalog($this->account(), DistributionConfig::fromArray([]));

        $nav = self::toArray($ctx['nav']);
        $navIds = array_map(static fn (array $entry): mixed => $entry['id'] ?? null, $nav);
        self::assertContains('dashboard', $navIds, 'The catalog nav carries the workspace home.');
        self::assertContains('documents', $navIds, 'The catalog nav carries the canonical modules.');

        // The home entry stays live (points at the real /admin/anokii).
        self::assertSame('/admin/anokii', self::navEntry($nav, 'dashboard')['href']);

        // Nothing enabled, so there are no live tool cards, only product previews.
        self::assertSame([], $ctx['live_cards']);
        self::assertNotEmpty(self::toArray($ctx['preview_cards']));
    }

    #[Test]
    public function a_module_is_preview_when_disabled_and_live_when_enabled(): void
    {
        $disabled = AnokiiShellContext::catalog($this->account(), DistributionConfig::fromArray([]));
        $documents = self::navEntry(self::toArray($disabled['nav']), 'documents');
        self::assertSame('/admin/anokii/m/documents', $documents['href'], 'Disabled module links to its coming-soon page.');
        self::assertSame('Preview', $documents['badge']);

        $enabled = AnokiiShellContext::catalog($this->account(), DistributionConfig::fromArray([
            'modules' => ['enabled' => ['documents']],
        ]));
        $documents = self::navEntry(self::toArray($enabled['nav']), 'documents');
        self::assertSame('/admin/anokii/documents', $documents['href'], 'Enabled module links to its real route.');
        self::assertSame('', $documents['badge']);

        $liveHrefs = array_map(static fn (array $card): mixed => $card['href'] ?? null, self::toArray($enabled['live_cards']));
        self::assertContains('/admin/anokii/documents', $liveHrefs, 'Enabled module appears as a live tool card.');
    }

    #[Test]
    public function coming_soon_is_null_for_an_unknown_module_and_returns_a_known_one(): void
    {
        self::assertNull(AnokiiShellContext::catalogComingSoon($this->account(), DistributionConfig::fromArray([]), 'not-a-module'));

        $ctx = AnokiiShellContext::catalogComingSoon($this->account(), DistributionConfig::fromArray([]), 'documents');
        self::assertNotNull($ctx);
        $module = self::toArray($ctx['module']);
        self::assertSame('documents', $module['id']);
    }

    private function account(): AccountInterface
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('getRoles')->willReturn(['admin']);

        return $account;
    }

    /**
     * @param list<array<string, mixed>> $nav
     *
     * @return array<string, mixed>
     */
    private static function navEntry(array $nav, string $id): array
    {
        foreach ($nav as $entry) {
            if (is_array($entry) && ($entry['id'] ?? null) === $id) {
                return $entry;
            }
        }

        self::fail("nav entry {$id} not found");
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function toArray(mixed $value): array
    {
        self::assertIsArray($value);

        /** @var list<array<string, mixed>> $value */
        return $value;
    }
}
