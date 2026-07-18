<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Media\UploadHandler;

#[CoversClass(UploadHandler::class)]
final class UploadHandlerTest extends TestCase
{
    private UploadHandler $service;

    /** @var list<string> */
    private array $tmpFiles = [];

    protected function setUp(): void
    {
        $this->service = new UploadHandler(sys_get_temp_dir() . '/upload_test_' . bin2hex(random_bytes(4)));
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $path) {
            @unlink($path);
        }
        $this->tmpFiles = [];
    }

    /**
     * Write real sniffable bytes to a temp file: validate() is sniff-only
     * (the client-declared 'type' is never consulted), so fixtures must
     * carry content that ext-fileinfo can identify.
     */
    private function tmpFile(string $bytes): string
    {
        $path = sys_get_temp_dir() . '/upload_fixture_' . bin2hex(random_bytes(4));
        file_put_contents($path, $bytes);
        $this->tmpFiles[] = $path;

        return $path;
    }

    /** A minimal valid 1x1 PNG. */
    private static function pngBytes(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
    }

    #[Test]
    public function validate_rejects_oversized_file(): void
    {
        $file = [
            'error' => UPLOAD_ERR_OK,
            'size' => 6_000_000,
            'type' => 'image/jpeg',
        ];

        $errors = $this->service->validate($file);

        self::assertContains('File must be under 5MB.', $errors);
    }

    #[Test]
    public function validate_rejects_non_image(): void
    {
        $file = [
            'error' => UPLOAD_ERR_OK,
            'size' => 1024,
            'tmp_name' => $this->tmpFile("%PDF-1.4\n%fake body\n"),
        ];

        $errors = $this->service->validate($file);

        self::assertContains('File type not allowed.', $errors);
    }

    #[Test]
    public function validate_accepts_valid_image(): void
    {
        $file = [
            'error' => UPLOAD_ERR_OK,
            'size' => 1024,
            'tmp_name' => $this->tmpFile(self::pngBytes()),
        ];

        $errors = $this->service->validate($file);

        self::assertSame([], $errors);
    }

    #[Test]
    public function validate_fails_closed_when_content_cannot_be_sniffed(): void
    {
        // No tmp_name: the sniff cannot run, and the client-declared 'type'
        // must never be trusted as a fallback.
        $file = [
            'error' => UPLOAD_ERR_OK,
            'size' => 1024,
            'type' => 'image/jpeg',
        ];

        $errors = $this->service->validate($file);

        self::assertContains('File type could not be verified.', $errors);
    }

    #[Test]
    public function generate_safe_filename_strips_unsafe_chars(): void
    {
        $filename = $this->service->generateSafeFilename('../my photo (1).png');

        self::assertMatchesRegularExpression('/^my_photo__1_[a-f0-9]{8}\.png$/', $filename);
        self::assertStringNotContainsString('..', $filename);
        self::assertStringNotContainsString(' ', $filename);
        self::assertStringEndsWith('.png', $filename);
    }

    #[Test]
    public function validate_rejects_upload_error(): void
    {
        $file = [
            'error' => UPLOAD_ERR_PARTIAL,
            'size' => 1024,
            'type' => 'image/jpeg',
        ];

        $errors = $this->service->validate($file);

        self::assertContains('Upload failed.', $errors);
        self::assertCount(1, $errors);
    }
}
