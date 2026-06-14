<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\Account;

use App\Entity\Account\SavedWord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SavedWord::class)]
final class SavedWordTest extends TestCase
{
    #[Test]
    public function carries_the_denormalised_snapshot(): void
    {
        $saved = new SavedWord([
            'word' => 'makwa',
            'user_id' => 5,
            'dictionary_entry_id' => 12737,
            'slug' => 'makwa',
            'definition' => '["a bear"]',
        ]);

        $this->assertSame('saved_word', $saved->getEntityTypeId());
        $this->assertSame('makwa', $saved->get('word'));
        $this->assertSame(5, $saved->get('user_id'));
        $this->assertSame(0, $saved->get('created_at'));
    }
}
