<?php

declare(strict_types=1);

namespace App\Tests\Unit\Language;

use App\Language\DialectCodeProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DialectCodeProvider::class)]
final class DialectCodeProviderTest extends TestCase
{
    #[Test]
    public function codes_include_the_canonical_dialects(): void
    {
        $codes = (new DialectCodeProvider())->codes();

        // The RHT nations map to these; the full set is the ConfigSeeder contract.
        self::assertContains('oji-east', $codes);
        self::assertContains('oji-ottawa', $codes);
        self::assertGreaterThanOrEqual(10, count($codes));
        self::assertSame(array_values(array_unique($codes)), $codes, 'Codes are unique.');
    }

    #[Test]
    public function is_valid_accepts_canonical_codes_and_rejects_others(): void
    {
        $provider = new DialectCodeProvider();

        self::assertTrue($provider->isValid('oji-east'));
        self::assertTrue($provider->isValid('mohawk'));
        self::assertFalse($provider->isValid('klingon'));
        self::assertFalse($provider->isValid(''));
        self::assertFalse($provider->isValid('OJI-EAST'), 'Validation is case-sensitive.');
    }

    #[Test]
    public function all_exposes_display_names_and_iso_codes(): void
    {
        $all = (new DialectCodeProvider())->all();

        $byCode = [];
        foreach ($all as $row) {
            $byCode[$row['code']] = $row;
        }

        self::assertArrayHasKey('oji-east', $byCode);
        self::assertSame('Eastern Ojibwe', $byCode['oji-east']['display_name']);
        self::assertSame('ojg', $byCode['oji-east']['iso_639_3']);
        self::assertNotSame('', $byCode['oji-east']['name'], 'The endonym is present.');
    }
}
