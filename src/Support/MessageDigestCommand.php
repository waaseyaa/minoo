<?php

declare(strict_types=1);

namespace Minoo\Support;

use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Mail\Envelope;
use Waaseyaa\Mail\MailerInterface;

final class MessageDigestCommand
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly MailerInterface $mailer,
        private readonly bool $mailConfigured,
        private readonly array $config,
    ) {}

    public function execute(): void
    {
        if (!$this->mailConfigured) {
            return;
        }

        $debounceMinutes = (int) ($this->config['digest_debounce'] ?? 15);
        $now = time();
        $debounceThreshold = $now - ($debounceMinutes * 60);

        $participantStorage = $this->entityTypeManager->getStorage('thread_participant');
        $messageStorage = $this->entityTypeManager->getStorage('thread_message');
        $userStorage = $this->entityTypeManager->getStorage('user');

        $allParticipantIds = $participantStorage->getQuery()->sort('user_id', 'ASC')->execute();
        if ($allParticipantIds === []) {
            return;
        }

        $allParticipants = array_values($participantStorage->loadMultiple($allParticipantIds));

        // Group by user
        $byUser = [];
        foreach ($allParticipants as $participant) {
            $userId = (int) $participant->get('user_id');
            $byUser[$userId][] = $participant;
        }

        foreach ($byUser as $userId => $userParticipants) {
            $user = $userStorage->load($userId);
            if ($user === null) {
                continue;
            }

            // Check notification preferences
            $prefs = $user->get('notification_preferences');
            if (is_string($prefs)) {
                $prefs = json_decode($prefs, true);
            }
            if (is_array($prefs) && isset($prefs['email_digest']) && $prefs['email_digest'] === false) {
                continue;
            }

            $email = (string) ($user->get('mail') ?? '');
            $name = (string) ($user->get('name') ?? '');
            if ($email === '') {
                continue;
            }

            $threadSummaries = [];
            $totalUnread = 0;

            foreach ($userParticipants as $participant) {
                $threadId = (int) $participant->get('thread_id');
                $lastReadAt = (int) $participant->get('last_read_at');

                $unreadIds = $messageStorage->getQuery()
                    ->condition('thread_id', $threadId)
                    ->condition('created_at', $lastReadAt, '>')
                    ->condition('created_at', $debounceThreshold, '<')
                    ->condition('deleted_at', null)
                    ->sort('created_at', 'DESC')
                    ->range(0, 5)
                    ->execute();

                if ($unreadIds === []) {
                    continue;
                }

                $unreadMessages = array_values($messageStorage->loadMultiple($unreadIds));
                $totalUnread += count($unreadIds);

                $preview = count($unreadMessages) > 0 ? (string) $unreadMessages[0]->get('body') : '';

                $threadSummaries[] = [
                    'thread_id' => $threadId,
                    'count' => count($unreadIds),
                    'preview' => mb_substr($preview, 0, 100),
                ];
            }

            if ($totalUnread === 0) {
                continue;
            }

            $this->sendDigestEmail($email, $name, $totalUnread, $threadSummaries);
        }
    }

    private function sendDigestEmail(string $email, string $name, int $totalUnread, array $summaries): void
    {
        $greeting = $name !== '' ? "Hey {$name}," : 'Hey,';
        $threadCount = count($summaries);
        $subject = "You have {$totalUnread} unread message" . ($totalUnread !== 1 ? 's' : '') . ' on Minoo';

        $body = "{$greeting}\n\nYou have unread messages in {$threadCount} conversation" . ($threadCount !== 1 ? 's' : '') . ":\n\n";

        foreach ($summaries as $summary) {
            $body .= "- Thread #{$summary['thread_id']} ({$summary['count']} new)\n";
            if ($summary['preview'] !== '') {
                $body .= "  \"{$summary['preview']}\"\n";
            }
        }

        $body .= "\nOpen Messages: https://minoo.sagamok.ca/messages\n";
        $body .= "\n--\nMinoo - Sagamok Anishnawbek Community Platform\n";

        $this->mailer->send(new Envelope(
            to: [$email],
            from: '',
            subject: $subject,
            textBody: $body,
        ));
    }
}
