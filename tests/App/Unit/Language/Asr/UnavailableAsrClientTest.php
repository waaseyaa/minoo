<?php

declare(strict_types=1);

namespace App\Tests\Unit\Language\Asr;

use App\Language\Asr\AsrClient;
use App\Language\Asr\AsrResult;
use App\Language\Asr\UnavailableAsrClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnavailableAsrClient::class)]
#[CoversClass(AsrResult::class)]
final class UnavailableAsrClientTest extends TestCase
{
    #[Test]
    public function it_is_the_asr_client_seam_and_is_never_available(): void
    {
        $client = new UnavailableAsrClient();

        self::assertInstanceOf(AsrClient::class, $client);
        self::assertFalse($client->isAvailable());
    }

    #[Test]
    public function transcribe_fails_closed_with_a_consent_gate_message(): void
    {
        $result = (new UnavailableAsrClient())->transcribe('corpus:reel-1', 'oj-x-sagamok');

        self::assertFalse($result->ok);
        self::assertSame('', $result->transcript);
        self::assertStringContainsString('Phase 0 consent agreement', $result->message);
    }

    #[Test]
    public function the_result_value_object_distinguishes_transcript_from_unavailable(): void
    {
        $ok = AsrResult::transcript('makwa');
        self::assertTrue($ok->ok);
        self::assertSame('makwa', $ok->transcript);

        $no = AsrResult::unavailable('gated');
        self::assertFalse($no->ok);
        self::assertSame('gated', $no->message);
    }
}
