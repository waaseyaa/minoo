<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Newsletter\Access;

use Minoo\Access\NewsletterAccessPolicy;
use Minoo\Entity\NewsletterEdition;
use Minoo\Entity\NewsletterItem;
use Minoo\Entity\NewsletterSubmission;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;

#[CoversClass(NewsletterAccessPolicy::class)]
final class NewsletterAccessPolicyTest extends TestCase
{
    #[Test]
    public function admin_can_view_any_edition_at_any_status(): void
    {
        $policy = new NewsletterAccessPolicy();
        $edition = new NewsletterEdition([
            'neid' => 1,
            'community_id' => 'wiikwemkoong',
            'status' => 'draft',
        ]);

        $admin = $this->makeAccount(['administer content']);

        $result = $policy->access($edition, 'view', $admin);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function public_can_view_sent_edition(): void
    {
        $policy = new NewsletterAccessPolicy();
        $edition = new NewsletterEdition([
            'neid' => 1,
            'community_id' => 'wiikwemkoong',
            'status' => 'sent',
        ]);

        $public = $this->makeAccount([]);

        $result = $policy->access($edition, 'view', $public);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function public_cannot_view_draft_edition(): void
    {
        $policy = new NewsletterAccessPolicy();
        $edition = new NewsletterEdition([
            'neid' => 1,
            'community_id' => 'wiikwemkoong',
            'status' => 'draft',
        ]);

        $public = $this->makeAccount([]);

        $result = $policy->access($edition, 'view', $public);

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function applies_to_all_three_newsletter_types(): void
    {
        $policy = new NewsletterAccessPolicy();
        $this->assertTrue($policy->appliesTo('newsletter_edition'));
        $this->assertTrue($policy->appliesTo('newsletter_item'));
        $this->assertTrue($policy->appliesTo('newsletter_submission'));
        $this->assertFalse($policy->appliesTo('event'));
    }

    #[Test]
    public function submitter_can_view_own_submission(): void
    {
        $policy = new NewsletterAccessPolicy();
        $submission = new NewsletterSubmission([
            'nsid' => 1,
            'community_id' => 'wiikwemkoong',
            'submitted_by' => 1,
        ]);

        $submitter = $this->makeAccount([], 1);

        $result = $policy->access($submission, 'view', $submitter);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function submitter_cannot_view_other_users_submission(): void
    {
        $policy = new NewsletterAccessPolicy();
        $submission = new NewsletterSubmission([
            'nsid' => 1,
            'community_id' => 'wiikwemkoong',
            'submitted_by' => 1,
        ]);

        $other = $this->makeAccount([], 99);

        $result = $policy->access($submission, 'view', $other);

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function coordinator_can_view_any_submission(): void
    {
        $policy = new NewsletterAccessPolicy();
        $submission = new NewsletterSubmission([
            'nsid' => 1,
            'community_id' => 'wiikwemkoong',
            'submitted_by' => 1,
        ]);

        $coordinator = $this->makeAccount(['coordinate community'], 42);

        $result = $policy->access($submission, 'view', $coordinator);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function public_cannot_view_submission(): void
    {
        $policy = new NewsletterAccessPolicy();
        $submission = new NewsletterSubmission([
            'nsid' => 1,
            'community_id' => 'wiikwemkoong',
            'submitted_by' => 1,
        ]);

        $public = $this->makeAccount([], 99);

        $result = $policy->access($submission, 'view', $public);

        $this->assertFalse($result->isAllowed());
    }

    #[Test]
    public function coordinator_can_update_draft_edition(): void
    {
        $policy = new NewsletterAccessPolicy();
        $edition = new NewsletterEdition([
            'neid' => 1,
            'community_id' => 'wiikwemkoong',
            'status' => 'draft',
        ]);

        $coordinator = $this->makeAccount(['coordinate community']);

        $result = $policy->access($edition, 'update', $coordinator);

        $this->assertTrue($result->isAllowed());
    }

    #[Test]
    public function newsletter_item_view_is_always_allowed(): void
    {
        $policy = new NewsletterAccessPolicy();
        $item = new NewsletterItem([
            'niid' => 1,
            'edition_id' => 1,
        ]);

        $public = $this->makeAccount([]);

        $result = $policy->access($item, 'view', $public);

        $this->assertTrue($result->isAllowed());
    }

    private function makeAccount(array $permissions, int $id = 1): AccountInterface
    {
        return new class($permissions, $id) implements AccountInterface {
            public function __construct(private array $perms, private int $uid) {}
            public function id(): int|string { return $this->uid; }
            public function isAuthenticated(): bool { return true; }
            public function hasPermission(string $permission): bool { return in_array($permission, $this->perms, true); }
            public function getRoles(): array { return []; }
        };
    }
}
