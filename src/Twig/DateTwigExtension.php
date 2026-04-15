<?php

declare(strict_types=1);

namespace App\Twig;

use Closure;
use DateTimeImmutable;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class DateTwigExtension extends AbstractExtension
{
    /** @var Closure(): DateTimeImmutable */
    private Closure $clock;

    /**
     * @param (Closure(): DateTimeImmutable)|DateTimeImmutable|null $clock
     *        Inject a fixed DateTimeImmutable or a closure returning one in tests.
     *        Defaults to "now" at call time.
     */
    public function __construct(Closure|DateTimeImmutable|null $clock = null)
    {
        if ($clock instanceof DateTimeImmutable) {
            $fixed = $clock;
            $this->clock = static fn (): DateTimeImmutable => $fixed;
        } elseif ($clock instanceof Closure) {
            $this->clock = $clock;
        } else {
            $this->clock = static fn (): DateTimeImmutable => new DateTimeImmutable('now');
        }
    }

    /** @return TwigFilter[] */
    public function getFilters(): array
    {
        return [
            new TwigFilter('friendly_date', $this->friendlyDate(...)),
            new TwigFilter('relative_date', $this->relativeDate(...)),
        ];
    }

    public function friendlyDate(?string $dateString): string
    {
        if ($dateString === null || $dateString === '') {
            return '';
        }

        try {
            $date = new DateTimeImmutable($dateString);
        } catch (\Exception) {
            return $dateString;
        }

        $now = ($this->clock)();
        $today = $now->format('Y-m-d');
        $tomorrow = $now->modify('+1 day')->format('Y-m-d');
        $dateDay = $date->format('Y-m-d');

        if ($dateDay === $today) {
            return 'Today, ' . $date->format('g:i A');
        }

        if ($dateDay === $tomorrow) {
            return 'Tomorrow, ' . $date->format('g:i A');
        }

        if ($date->format('Y') === $now->format('Y')) {
            return $date->format('M j, g:i A');
        }

        return $date->format('M j, Y');
    }

    /**
     * Return a short human-readable relative label for a timestamp.
     *
     * Mapping (consistent, no library):
     *   past:   "N minutes ago" (<1h), "N hours ago" (<1d same calendar day),
     *           "yesterday", "N days ago" (<7d),
     *           "last week" (7-13d), "N weeks ago" (14-29d),
     *           "last month" (~30-59d), "N months ago" (60-364d),
     *           "last year" / "N years ago"
     *   today:  "today"
     *   future: symmetric ("in N minutes", "tomorrow", "in N days",
     *           "next week", "in N weeks", "next month", "in N months",
     *           "next year", "in N years")
     *
     * @param int|string|\DateTimeInterface|null $value unix timestamp, datetime-parseable string, or DateTime
     */
    public function relativeDate(int|string|\DateTimeInterface|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            if ($value instanceof \DateTimeInterface) {
                $date = DateTimeImmutable::createFromInterface($value);
            } elseif (is_int($value)) {
                $date = (new DateTimeImmutable('@' . $value))->setTimezone((($this->clock)())->getTimezone());
            } else {
                $date = new DateTimeImmutable($value);
            }
        } catch (\Exception) {
            return (string) $value;
        }

        $now = ($this->clock)();

        $todayStart = $now->setTime(0, 0, 0);
        $dateStart = $date->setTime(0, 0, 0);
        $dayDiff = (int) $todayStart->diff($dateStart)->format('%r%a');

        if ($dayDiff === 0) {
            // Within the same calendar day. Use minutes/hours for precision.
            $secondsDiff = $date->getTimestamp() - $now->getTimestamp();
            $absSeconds = abs($secondsDiff);

            if ($absSeconds < 60) {
                return 'today';
            }

            if ($absSeconds < 3600) {
                $minutes = (int) round($absSeconds / 60);
                return $secondsDiff < 0
                    ? "{$minutes} minutes ago"
                    : "in {$minutes} minutes";
            }

            $hours = (int) round($absSeconds / 3600);
            if ($hours === 0) {
                return 'today';
            }

            return $secondsDiff < 0
                ? "{$hours} hours ago"
                : "in {$hours} hours";
        }

        $future = $dayDiff > 0;
        $absDays = abs($dayDiff);

        if ($absDays === 1) {
            return $future ? 'tomorrow' : 'yesterday';
        }

        if ($absDays < 7) {
            return $future ? "in {$absDays} days" : "{$absDays} days ago";
        }

        if ($absDays < 14) {
            return $future ? 'next week' : 'last week';
        }

        if ($absDays < 30) {
            $weeks = (int) floor($absDays / 7);
            return $future ? "in {$weeks} weeks" : "{$weeks} weeks ago";
        }

        if ($absDays < 60) {
            return $future ? 'next month' : 'last month';
        }

        if ($absDays < 365) {
            $months = (int) floor($absDays / 30);
            return $future ? "in {$months} months" : "{$months} months ago";
        }

        $years = (int) floor($absDays / 365);
        if ($years === 1) {
            return $future ? 'next year' : 'last year';
        }

        return $future ? "in {$years} years" : "{$years} years ago";
    }
}
