<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\MediaCopyright;

use Minoo\Entity\Event;
use Minoo\Entity\Group;
use Minoo\Entity\Teaching;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Teaching::class)]
#[CoversClass(Event::class)]
#[CoversClass(Group::class)]
final class CopyrightFieldTest extends TestCase
{
    #[Test]
    public function teaching_copyright_status_defaults_to_unknown(): void
    {
        $teaching = new Teaching([
            'title' => 'Test Teaching',
            'type' => 'culture',
            'content' => 'Content here.',
        ]);

        $this->assertSame('unknown', $teaching->get('copyright_status'));
    }

    #[Test]
    public function teaching_can_set_copyright_status(): void
    {
        $teaching = new Teaching([
            'title' => 'Test Teaching',
            'type' => 'culture',
            'content' => 'Content here.',
            'copyright_status' => 'community_owned',
        ]);

        $this->assertSame('community_owned', $teaching->get('copyright_status'));
    }

    #[Test]
    public function event_copyright_status_defaults_to_unknown(): void
    {
        $event = new Event([
            'title' => 'Test Event',
            'type' => 'gathering',
        ]);

        $this->assertSame('unknown', $event->get('copyright_status'));
    }

    #[Test]
    public function group_copyright_status_defaults_to_unknown(): void
    {
        $group = new Group([
            'name' => 'Test Group',
            'type' => 'community',
        ]);

        $this->assertSame('unknown', $group->get('copyright_status'));
    }

    #[Test]
    public function copyright_status_accepts_all_valid_values(): void
    {
        $validValues = ['community_owned', 'cc_by_nc_sa', 'requires_permission', 'unknown'];

        foreach ($validValues as $value) {
            $teaching = new Teaching([
                'title' => 'Test Teaching',
                'type' => 'culture',
                'content' => 'Content here.',
                'copyright_status' => $value,
            ]);

            $this->assertSame($value, $teaching->get('copyright_status'), "Failed for value: {$value}");
        }
    }
}
