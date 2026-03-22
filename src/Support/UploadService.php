<?php

declare(strict_types=1);

namespace Minoo\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class UploadService
{
    private const int MAX_SIZE = 5_242_880; // 5MB

    private const array ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(private readonly string $basePath) {}

    /** @return string[] validation errors */
    public function validateImage(array $file): array
    {
        $errors = [];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload failed.';

            return $errors;
        }

        if (($file['size'] ?? 0) > self::MAX_SIZE) {
            $errors[] = 'Image must be under 5MB.';
        }

        if (! in_array($file['type'] ?? '', self::ALLOWED_TYPES, true)) {
            $errors[] = 'Only JPEG, PNG, GIF, and WebP images are allowed.';
        }

        return $errors;
    }

    public function generateSafeFilename(string $original): string
    {
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $name = pathinfo($original, PATHINFO_FILENAME);
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        $safe = trim($safe, '_');

        if ($safe === '') {
            $safe = 'upload';
        }

        return $safe . '_' . bin2hex(random_bytes(4)) . '.' . ($ext ?: 'jpg');
    }

    /** @return string relative path from basePath */
    public function moveUpload(array $file, string $subdir): string
    {
        $targetDir = $this->basePath . '/' . $subdir;

        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filename = $this->generateSafeFilename($file['name'] ?? 'upload.jpg');
        $targetPath = $targetDir . '/' . $filename;
        move_uploaded_file($file['tmp_name'], $targetPath);

        return $subdir . '/' . $filename;
    }

    public function deleteDirectory(string $subdir): void
    {
        $dir = $this->basePath . '/' . $subdir;

        if (! is_dir($dir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($dir);
    }
}
