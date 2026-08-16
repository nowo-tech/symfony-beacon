<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Entity;

use App\Identity\Entity\User;
use App\Issues\Entity\IssueMention;
use PHPUnit\Framework\TestCase;

final class IssueMentionCoverageCloseTest extends TestCase
{
    public function testMentionedUserAndReadTimestampAccessors(): void
    {
        $user = new User();
        $mention = new IssueMention();

        $mention->setMentionedUser($user);
        self::assertSame($user, $mention->getMentionedUser());
        self::assertNull($mention->getReadAt());
    }
}
