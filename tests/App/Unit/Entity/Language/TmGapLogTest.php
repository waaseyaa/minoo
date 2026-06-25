<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\Language;

use App\Entity\Language\TmGapLog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TmGapLog::class)]
final class TmGapLogTest extends TestCase
{
    #[Test]
    public function it_creates_with_the_missed_source(): void
    {
        $gap = new TmGapLog([
            'source_en' => 'snowmobile',
            'language_tag' => 'oj-x-sagamok',
            'lookup_type' => 'exact_miss',
        ]);

        $this->assertSame('snowmobile', $gap->get('source_en'));
        $this->assertSame('oj-x-sagamok', $gap->get('language_tag'));
        $this->assertSame('exact_miss', $gap->get('lookup_type'));
        $this->assertSame('tm_gap_log', $gap->getEntityTypeId());
    }

    #[Test]
    public function it_defaults_to_an_open_status_with_one_request(): void
    {
        $gap = new TmGapLog(['source_en' => 'helicopter']);

        $this->assertSame('open', $gap->get('status'));
        $this->assertSame(1, $gap->get('request_count'));
    }

    #[Test]
    public function explicit_values_override_defaults(): void
    {
        $gap = new TmGapLog([
            'source_en' => 'helicopter',
            'status' => 'queued_for_speaker',
            'request_count' => 7,
        ]);

        $this->assertSame('queued_for_speaker', $gap->get('status'));
        $this->assertSame(7, $gap->get('request_count'));
    }
}
