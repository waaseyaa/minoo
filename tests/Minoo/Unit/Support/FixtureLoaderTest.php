<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Support;

use Minoo\Support\FixtureLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FixtureLoader::class)]
final class FixtureLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/minoo-fixtures-' . uniqid();
        mkdir($this->tempDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*'));
        rmdir($this->tempDir);
    }

    #[Test]
    public function loadsValidBusinessesJson(): void
    {
        file_put_contents($this->tempDir . '/businesses.json', json_encode([
            [
                'name' => 'Test Business',
                'slug' => 'test-business',
                'type' => 'business',
                'community' => 'Test Town',
            ],
        ]));

        $loader = new FixtureLoader($this->tempDir);
        $result = $loader->load('businesses');

        $this->assertCount(1, $result);
        $this->assertSame('test-business', $result[0]['slug']);
    }

    #[Test]
    public function rejectsInvalidJson(): void
    {
        file_put_contents($this->tempDir . '/businesses.json', 'not json');

        $loader = new FixtureLoader($this->tempDir);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON');
        $loader->load('businesses');
    }

    #[Test]
    public function returnsMissingFileAsEmptyArray(): void
    {
        $loader = new FixtureLoader($this->tempDir);
        $result = $loader->load('nonexistent');

        $this->assertSame([], $result);
    }

    #[Test]
    public function validateBusinessRequiredFields(): void
    {
        $loader = new FixtureLoader($this->tempDir);
        $errors = $loader->validate([['name' => 'No Slug']], 'businesses');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('slug', $errors[0]);
    }

    #[Test]
    public function validatePeopleRequiredFields(): void
    {
        $loader = new FixtureLoader($this->tempDir);
        $errors = $loader->validate([['slug' => 'has-slug']], 'people');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('name', $errors[0]);
    }

    #[Test]
    public function validateEventsRequiredFields(): void
    {
        $loader = new FixtureLoader($this->tempDir);
        $errors = $loader->validate([
            ['title' => 'Event', 'slug' => 'event', 'type' => 'gathering', 'community' => 'Town'],
        ], 'events');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('starts_at', $errors[0]);
    }

    #[Test]
    public function validateRejectsDuplicateSlugs(): void
    {
        $loader = new FixtureLoader($this->tempDir);
        $errors = $loader->validate([
            ['name' => 'A', 'slug' => 'same-slug', 'type' => 'business', 'community' => 'Town'],
            ['name' => 'B', 'slug' => 'same-slug', 'type' => 'business', 'community' => 'Town'],
        ], 'businesses');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('duplicate', strtolower($errors[0]));
    }

    #[Test]
    public function validatePhoneFormatWhenPresent(): void
    {
        $loader = new FixtureLoader($this->tempDir);
        $errors = $loader->validate([
            ['name' => 'Biz', 'slug' => 'biz', 'type' => 'business', 'community' => 'Town', 'phone' => 'not-a-phone'],
        ], 'businesses');

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('phone', strtolower($errors[0]));
    }

    #[Test]
    public function validateAcceptsE164Phone(): void
    {
        $loader = new FixtureLoader($this->tempDir);
        $errors = $loader->validate([
            ['name' => 'Biz', 'slug' => 'biz', 'type' => 'business', 'community' => 'Town', 'phone' => '+17058698163'],
        ], 'businesses');

        $this->assertSame([], $errors);
    }
}
