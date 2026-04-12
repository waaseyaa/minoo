<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Newsletter\Service;

use Minoo\Domain\Newsletter\Exception\DispatchException;
use Minoo\Domain\Newsletter\Service\NewsletterDispatcher;
use Minoo\Entity\NewsletterEdition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NewsletterDispatcher::class)]
#[CoversClass(DispatchException::class)]
final class NewsletterDispatcherTest extends TestCase
{
    #[Test]
    public function unconfigured_mail_service_throws(): void
    {
        $mailFake = new class {
            public function isConfigured(): bool
            {
                return false;
            }

            public function sendWithAttachment(string $to, string $subject, string $body, string $path): bool
            {
                return true;
            }
        };

        $dispatcher = new NewsletterDispatcher(
            mailService: $mailFake,
            communityConfig: ['manitoulin-regional' => ['printer_email' => 'sales@example.com']],
            defaultCommunity: 'manitoulin-regional',
        );

        $edition = new NewsletterEdition([
            'neid' => 1,
            'community_id' => 'manitoulin-regional',
            'status' => 'generated',
            'pdf_path' => '/tmp/issue.pdf',
        ]);

        $this->expectException(DispatchException::class);
        $this->expectExceptionMessage('Mail service is not configured');

        $dispatcher->dispatch($edition);
    }

    #[Test]
    public function missing_printer_email_throws(): void
    {
        $mailFake = new class {
            public function isConfigured(): bool
            {
                return true;
            }

            public function sendWithAttachment(string $to, string $subject, string $body, string $path): bool
            {
                return true;
            }
        };

        $dispatcher = new NewsletterDispatcher(
            mailService: $mailFake,
            communityConfig: ['manitoulin-regional' => ['printer_email' => '']],
            defaultCommunity: 'manitoulin-regional',
        );

        $edition = new NewsletterEdition([
            'neid' => 1,
            'community_id' => 'manitoulin-regional',
            'status' => 'generated',
            'pdf_path' => '/tmp/issue.pdf',
        ]);

        $this->expectException(DispatchException::class);
        $this->expectExceptionMessage('No printer_email configured');

        $dispatcher->dispatch($edition);
    }

    #[Test]
    public function successful_dispatch_returns_recipient(): void
    {
        $mailFake = new class {
            public ?string $sentTo = null;
            public ?string $sentSubject = null;
            public ?string $sentPath = null;

            public function isConfigured(): bool
            {
                return true;
            }

            public function sendWithAttachment(string $to, string $subject, string $body, string $path): bool
            {
                $this->sentTo = $to;
                $this->sentSubject = $subject;
                $this->sentPath = $path;
                return true;
            }
        };

        $tmp = tempnam(sys_get_temp_dir(), 'newsletter-test-') . '.pdf';
        file_put_contents($tmp, '%PDF-1.4 fake');

        $dispatcher = new NewsletterDispatcher(
            mailService: $mailFake,
            communityConfig: [
                'manitoulin-regional' => [
                    'printer_email' => 'sales@ojgraphix.com',
                    'printer_name' => 'OJ Graphix',
                ],
            ],
            defaultCommunity: 'manitoulin-regional',
        );

        $edition = new NewsletterEdition([
            'neid' => 42,
            'community_id' => 'manitoulin-regional',
            'volume' => 1,
            'issue_number' => 7,
            'status' => 'generated',
            'pdf_path' => $tmp,
            'headline' => 'Issue 7',
        ]);

        $recipient = $dispatcher->dispatch($edition);

        $this->assertSame('sales@ojgraphix.com', $recipient);
        $this->assertSame('sales@ojgraphix.com', $mailFake->sentTo);
        $this->assertSame($tmp, $mailFake->sentPath);
        $this->assertNotNull($mailFake->sentSubject);
        $this->assertStringContainsString('Issue 7', $mailFake->sentSubject);

        @unlink($tmp);
    }

    #[Test]
    public function null_community_edition_uses_default_community_config(): void
    {
        $mail = new class {
            public ?string $sentTo = null;
            public function isConfigured(): bool { return true; }
            public function sendWithAttachment(string $to, string $subj, string $body, string $path): bool {
                $this->sentTo = $to;
                return true;
            }
        };

        $dispatcher = new NewsletterDispatcher(
            mailService: $mail,
            communityConfig: ['manitoulin-regional' => ['printer_email' => 'default@example.com']],
            defaultCommunity: 'manitoulin-regional',
        );

        // Create a fake PDF file so the dispatcher's is_file() check passes
        $pdfPath = tempnam(sys_get_temp_dir(), 'ndt-');
        file_put_contents($pdfPath, 'FAKEPDF');

        $edition = new NewsletterEdition([
            'neid' => 1,
            'community_id' => null, // regional issue
            'status' => 'generated',
            'pdf_path' => $pdfPath,
            'volume' => 1,
            'issue_number' => 1,
            'publish_date' => '2026-04-15',
        ]);

        $recipient = $dispatcher->dispatch($edition);

        $this->assertSame('default@example.com', $recipient);
        $this->assertSame('default@example.com', $mail->sentTo);

        @unlink($pdfPath);
    }
}
