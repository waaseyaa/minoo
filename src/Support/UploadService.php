<?php

declare(strict_types=1);

namespace Minoo\Support;

final class UploadService
{
    private const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5 MB
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(
        private readonly string $uploadRoot,
    ) {}

    /**
     * Validate an uploaded image file.
     *
     * @param array{name: string, tmp_name: string, size: int, type: string, error: int} $file
     * @return list<string> Validation errors (empty = valid)
     */
    public function validateImage(array $file): array
    {
        $errors = [];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload failed';
            return $errors;
        }

        if (($file['size'] ?? 0) > self::MAX_IMAGE_SIZE) {
            $errors[] = 'Image must be under 5 MB';
        }

        $mime = $file['type'] ?? '';
        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            $errors[] = 'Only JPEG, PNG, GIF, and WebP images are allowed';
        }

        return $errors;
    }

    /**
     * Move an uploaded file to the uploads directory.
     *
     * @param array{name: string, tmp_name: string, size: int, type: string, error: int} $file
     * @param string $subdirectory e.g. "posts/42"
     * @return string Relative path from uploads root (e.g. "posts/42/abc123.jpg")
     */
    public function moveUpload(array $file, string $subdirectory): string
    {
        $dir = rtrim($this->uploadRoot, '/') . '/' . trim($subdirectory, '/');

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        $ext = $this->extensionFromMime($file['type'] ?? '');
        $filename = bin2hex(random_bytes(8)) . $ext;
        $destination = $dir . '/' . $filename;

        $tmpName = $file['tmp_name'] ?? '';

        if (is_uploaded_file($tmpName)) {
            move_uploaded_file($tmpName, $destination);
        } else {
            // Fallback for testing or non-standard environments
            copy($tmpName, $destination);
        }

        return trim($subdirectory, '/') . '/' . $filename;
    }

    /**
     * Delete all files in a subdirectory (e.g. when deleting a post).
     */
    public function deleteDirectory(string $subdirectory): void
    {
        $dir = rtrim($this->uploadRoot, '/') . '/' . trim($subdirectory, '/');

        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        @rmdir($dir);
    }

    private function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/gif' => '.gif',
            'image/webp' => '.webp',
            default => '.bin',
        };
    }
}
