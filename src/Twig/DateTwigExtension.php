<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class DateTwigExtension extends AbstractExtension
{
    /** @return TwigFilter[] */
    public function getFilters(): array
    {
        return [
            new TwigFilter('friendly_date', $this->friendlyDate(...)),
        ];
    }

    public function friendlyDate(?string $dateString): string
    {
        if ($dateString === null || $dateString === '') {
            return '';
        }

        try {
            $date = new \DateTimeImmutable($dateString);
        } catch (\Exception) {
            return $dateString;
        }

        $now = new \DateTimeImmutable();
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
}
