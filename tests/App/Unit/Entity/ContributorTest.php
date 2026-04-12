<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Contributor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Contributor::class)]
final class ContributorTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $contributor = new Contributor([
            'name' => 'Larry Smallwood',
            'code' => 'ls',
        ]);

        $this->assertSame('Larry Smallwood', $contributor->get('name'));
        $this->assertSame('ls', $contributor->get('code'));
        $this->assertSame('contributor', $contributor->getEntityTypeId());
    }

    #[Test]
    public function it_defaults_consent_to_zero(): void
    {
        $contributor = new Contributor(['name' => 'Test']);

        $this->assertSame(0, $contributor->get('consent_public'));
        $this->assertSame(0, $contributor->get('consent_record'));
        $this->assertSame(1, $contributor->get('status'));
    }

    #[Test]
    public function it_supports_bio_and_role(): void
    {
        $contributor = new Contributor([
            'name' => 'Eugene Stillday',
            'code' => 'es',
            'bio' => 'Elder and language keeper.',
            'role' => 'speaker',
            'media_id' => 10,
        ]);

        $this->assertSame('Elder and language keeper.', $contributor->get('bio'));
        $this->assertSame('speaker', $contributor->get('role'));
        $this->assertSame(10, $contributor->get('media_id'));
    }
}
