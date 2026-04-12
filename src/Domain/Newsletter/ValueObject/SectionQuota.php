<?php
declare(strict_types=1);

namespace App\Domain\Newsletter\ValueObject;

final readonly class SectionQuota
{
    /**
     * @param list<string> $sources
     */
    public function __construct(
        public string $name,
        public int $quota,
        public array $sources,
    ) {
        if ($quota <= 0) {
            throw new \InvalidArgumentException("SectionQuota requires a positive quota, got {$quota}");
        }
        if ($sources === []) {
            throw new \InvalidArgumentException("SectionQuota '{$name}' has no sources");
        }
    }

    /**
     * @param array<string, array{quota: int, sources: list<string>}> $config
     * @return list<self>
     */
    public static function fromConfig(array $config): array
    {
        $quotas = [];
        foreach ($config as $name => $row) {
            $quotas[] = new self((string) $name, (int) $row['quota'], (array) $row['sources']);
        }
        return $quotas;
    }
}
