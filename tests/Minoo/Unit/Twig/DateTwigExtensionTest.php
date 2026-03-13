<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Twig;

use Minoo\Twig\DateTwigExtension;
use PHPUnit\Framework\Attributes\CoversClass;
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
        $filters = $this->ext->getFilters();

        self::assertCount(1, $filters);
        self::assertSame('friendly_date', $filters[0]->getName());
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
}
