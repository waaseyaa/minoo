<?php

declare(strict_types=1);

namespace Minoo\Support;

use SendGrid;
use SendGrid\Mail\Mail;

class MailService
{
    private SendGrid $client;
    private string $apiKey;
    private string $fromAddress;
    private string $fromName;

    public function __construct(string $apiKey, string $fromAddress, string $fromName)
    {
        $this->client = new SendGrid($apiKey);
        $this->apiKey = $apiKey;
        $this->fromAddress = $fromAddress;
        $this->fromName = $fromName;
    }

    /**
     * Send a plain-text email.
     *
     * @return int HTTP status code from SendGrid (202 = accepted)
     * @throws \RuntimeException on failure
     */
    public function sendPlain(string $to, string $subject, string $body): int
    {
        $email = new Mail();
        $email->setFrom($this->fromAddress, $this->fromName);
        $email->setSubject($subject);
        $email->addTo($to);
        $email->addContent('text/plain', $body);

        return $this->dispatch($email);
    }

    /**
     * Send an HTML email with optional plain-text fallback.
     *
     * @return int HTTP status code from SendGrid (202 = accepted)
     * @throws \RuntimeException on failure
     */
    public function sendHtml(string $to, string $subject, string $html, string $plainText = ''): int
    {
        $email = new Mail();
        $email->setFrom($this->fromAddress, $this->fromName);
        $email->setSubject($subject);
        $email->addTo($to);
        if ($plainText !== '') {
            $email->addContent('text/plain', $plainText);
        }
        $email->addContent('text/html', $html);

        return $this->dispatch($email);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->fromAddress !== '';
    }

    private function dispatch(Mail $email): int
    {
        $response = $this->client->send($email);
        $statusCode = $response->statusCode();

        if ($statusCode >= 400) {
            throw new \RuntimeException(sprintf(
                'SendGrid returned HTTP %d: %s',
                $statusCode,
                $response->body(),
            ));
        }

        return $statusCode;
    }
}
