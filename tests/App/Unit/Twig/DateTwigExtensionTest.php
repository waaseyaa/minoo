<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Twig\DateTwigExtension;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DateTwigExtension::class)]
final class DateTwigExtensionTest extends TestCase
{
    private DateTwigExtension $ext;

    protected function setUp(): void
    {
        $this->ext = new DateTwigExtension();
    }

    #[Test]
    public function registersFilterNamedFriendlyDate(): void
    {
        $names = array_map(
            static fn ($f) => $f->getName(),
            $this->ext->getFilters(),
        );

        self::assertContains('friendly_date', $names);
        self::assertContains('relative_date', $names);
    }

    #[Test]
    public function returnsEmptyStringForNull(): void
    {
        self::assertSame('', $this->ext->friendlyDate(null));
    }

    #[Test]
    public function returnsEmptyStringForEmptyString(): void
    {
        self::assertSame('', $this->ext->friendlyDate(''));
    }

    #[Test]
    public function returnsOriginalStringForInvalidDate(): void
    {
        self::assertSame('not-a-date', $this->ext->friendlyDate('not-a-date'));
    }

    #[Test]
    public function formatsTodayWithTime(): void
    {
        $today = (new \DateTimeImmutable())->format('Y-m-d') . ' 14:30:00';

        $result = $this->ext->friendlyDate($today);

        self::assertStringStartsWith('Today, ', $result);
        self::assertStringContainsString('2:30 PM', $result);
    }

    #[Test]
    public function formatsTomorrowWithTime(): void
    {
        $tomorrow = (new \DateTimeImmutable('+1 day'))->format('Y-m-d') . ' 09:00:00';

        $result = $this->ext->friendlyDate($tomorrow);

        self::assertStringStartsWith('Tomorrow, ', $result);
    }

    #[Test]
    public function formatsSameYearWithoutYear(): void
    {
        $date = (new \DateTimeImmutable())->format('Y') . '-06-15 10:00:00';

        $result = $this->ext->friendlyDate($date);

        self::assertStringContainsString('Jun 15', $result);
        self::assertStringNotContainsString((new \DateTimeImmutable())->format('Y'), $result);
    }

    #[Test]
    public function formatsDifferentYearWithYear(): void
    {
        $result = $this->ext->friendlyDate('2020-03-15 10:00:00');

        self::assertSame('Mar 15, 2020', $result);
    }

    private const RELATIVE_NOW = '2026-04-15 12:00:00';

    private function pinned(): DateTwigExtension
    {
        return new DateTwigExtension(new DateTimeImmutable(self::RELATIVE_NOW));
    }

    #[Test]
    public function relativeDateReturnsEmptyForBlank(): void
    {
        self::assertSame('', $this->pinned()->relativeDate(null));
        self::assertSame('', $this->pinned()->relativeDate(''));
    }

    #[Test]
    public function relativeDateReturnsOriginalForInvalidString(): void
    {
        self::assertSame('not a date', $this->pinned()->relativeDate('not a date'));
    }

    #[Test]
    #[DataProvider('relativeCases')]
    public function relativeDateRendersExpectedLabel(int|string $input, string $expected): void
    {
        self::assertSame($expected, $this->pinned()->relativeDate($input));
    }

    /** @return iterable<string, array{0: int|string, 1: string}> */
    public static function relativeCases(): iterable
    {
        $now = new DateTimeImmutable(self::RELATIVE_NOW);

        yield 'today (same moment)' => [self::RELATIVE_NOW, 'today'];
        yield 'two hours ago' => [$now->modify('-2 hours')->format('Y-m-d H:i:s'), '2 hours ago'];
        yield 'in three hours' => [$now->modify('+3 hours')->format('Y-m-d H:i:s'), 'in 3 hours'];
        yield 'in 45 minutes' => [$now->modify('+45 minutes')->format('Y-m-d H:i:s'), 'in 45 minutes'];
        yield '45 minutes ago' => [$now->modify('-45 minutes')->format('Y-m-d H:i:s'), '45 minutes ago'];

        yield 'yesterday' => [$now->modify('-1 day')->format('Y-m-d H:i:s'), 'yesterday'];
        yield 'tomorrow' => [$now->modify('+1 day')->format('Y-m-d H:i:s'), 'tomorrow'];

        yield '3 days ago' => [$now->modify('-3 days')->format('Y-m-d H:i:s'), '3 days ago'];
        yield 'in 5 days' => [$now->modify('+5 days')->format('Y-m-d H:i:s'), 'in 5 days'];

        yield 'last week' => [$now->modify('-10 days')->format('Y-m-d H:i:s'), 'last week'];
        yield 'next week' => [$now->modify('+10 days')->format('Y-m-d H:i:s'), 'next week'];

        yield '2 weeks ago' => [$now->modify('-15 days')->format('Y-m-d H:i:s'), '2 weeks ago'];
        yield 'in 3 weeks' => [$now->modify('+21 days')->format('Y-m-d H:i:s'), 'in 3 weeks'];

        yield 'last month' => [$now->modify('-45 days')->format('Y-m-d H:i:s'), 'last month'];
        yield 'next month' => [$now->modify('+45 days')->format('Y-m-d H:i:s'), 'next month'];

        yield '3 months ago' => [$now->modify('-100 days')->format('Y-m-d H:i:s'), '3 months ago'];
        yield 'in 2 months' => [$now->modify('+65 days')->format('Y-m-d H:i:s'), 'in 2 months'];

        yield 'last year' => [$now->modify('-400 days')->format('Y-m-d H:i:s'), 'last year'];
        yield 'next year' => [$now->modify('+400 days')->format('Y-m-d H:i:s'), 'next year'];

        yield '3 years ago' => [$now->modify('-1100 days')->format('Y-m-d H:i:s'), '3 years ago'];
        yield 'in 3 years' => [$now->modify('+1100 days')->format('Y-m-d H:i:s'), 'in 3 years'];

        yield 'unix timestamp yesterday' => [$now->modify('-1 day')->getTimestamp(), 'yesterday'];
    }
}
