<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Support;

use Minoo\Support\MercurePublisher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MercurePublisher::class)]
final class MercurePublisherTest extends TestCase
{
    #[Test]
    public function build_post_body_produces_correct_url_encoded_string(): void
    {
        $publisher = new MercurePublisher('http://hub.example.com/.well-known/mercure', 'test-jwt');

        $body = $publisher->buildPostBody('/threads/42', ['type' => 'new_message', 'threadId' => 42]);

        $parsed = [];
        parse_str($body, $parsed);

        $this->assertSame('/threads/42', $parsed['topic']);
        $this->assertSame('{"type":"new_message","threadId":42}', $parsed['data']);
    }

    #[Test]
    public function is_configured_returns_false_when_jwt_is_empty(): void
    {
        $publisher = new MercurePublisher('http://hub.example.com/.well-known/mercure', '');

        $this->assertFalse($publisher->isConfigured());
    }

    #[Test]
    public function is_configured_returns_false_when_hub_url_is_empty(): void
    {
        $publisher = new MercurePublisher('', 'some-jwt');

        $this->assertFalse($publisher->isConfigured());
    }

    #[Test]
    public function is_configured_returns_true_when_both_values_set(): void
    {
        $publisher = new MercurePublisher('http://hub.example.com/.well-known/mercure', 'test-jwt');

        $this->assertTrue($publisher->isConfigured());
    }

    #[Test]
    public function publish_returns_false_when_not_configured(): void
    {
        $publisher = new MercurePublisher('', '');

        $this->assertFalse($publisher->publish('/threads/1', ['msg' => 'hello']));
    }
}
