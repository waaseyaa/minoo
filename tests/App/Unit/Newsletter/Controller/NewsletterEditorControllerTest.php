<?php

declare(strict_types=1);

namespace App\Tests\Unit\Newsletter\Controller;

use App\Controller\NewsletterEditorController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NewsletterEditorController::class)]
final class NewsletterEditorControllerTest extends TestCase
{
    #[Test]
    public function class_exists(): void
    {
        $this->assertTrue(class_exists(NewsletterEditorController::class));
    }
    // Real behavior tests live in NewsletterEndToEndTest (Task 12) — controller wiring
    // is exercised under a real kernel boot there.
}
