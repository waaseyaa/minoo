<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use Waaseyaa\Media\UploadHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UploadHandler::class)]
final class UploadHandlerTest extends TestCase
{
    private UploadHandler $service;

    protected function setUp(): void
    {
        $this->service = new UploadHandler(sys_get_temp_dir() . '/upload_test_' . bin2hex(random_bytes(4)));
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
            'type' => 'application/pdf',
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
            'type' => 'image/jpeg',
        ];

        $errors = $this->service->validate($file);

        self::assertSame([], $errors);
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
