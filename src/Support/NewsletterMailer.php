<?php

declare(strict_types=1);

namespace App\Support;

use SendGrid;
use SendGrid\Mail\Mail;
use Waaseyaa\Mail\MailDriverInterface;

/**
 * Thin SendGrid adapter that adds attachment support on top of the framework's
 * MailDriverInterface (which only supports plain text/html bodies).
 *
 * Used by NewsletterDispatcher. The framework Mail driver remains the source
 * of truth for whether mail is configured (isConfigured() proxies through).
 *
 * NOTE: This class has no unit tests — the SendGrid SDK client is constructed
 * inline and cannot be mocked without a refactor to inject a factory. It is
 * covered only by manual smoke tests against SendGrid staging. If you refactor
 * to inject a SendGrid\Client, add tests at that time.
 */
final class NewsletterMailer
{
    public function __construct(
        private readonly MailDriverInterface $driver,
        private readonly string $apiKey,
        private readonly string $fromAddress,
        private readonly string $fromName = 'Minoo Newsroom',
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->driver->isConfigured() && $this->apiKey !== '' && $this->fromAddress !== '';
    }

    public function sendWithAttachment(string $to, string $subject, string $body, string $path): bool
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('NewsletterMailer is not configured.');
        }

        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('Attachment not found: %s', $path));
        }

        $email = new Mail();
        $email->setFrom($this->fromAddress, $this->fromName);
        $email->setSubject($subject);
        $email->addTo($to);
        $email->addContent('text/plain', $body);

        $email->addAttachment(
            base64_encode((string) file_get_contents($path)),
            'application/pdf',
            basename($path),
            'attachment',
        );

        $client = new SendGrid($this->apiKey);
        $response = $client->send($email);
        $statusCode = $response->statusCode();

        if ($statusCode >= 400) {
            throw new \RuntimeException(sprintf('SendGrid returned HTTP %d: %s', $statusCode, $response->body()));
        }

        return true;
    }
}
