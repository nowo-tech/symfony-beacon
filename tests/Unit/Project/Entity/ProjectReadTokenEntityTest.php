<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Entity;

use App\Identity\Entity\User;
use App\Project\Entity\ProjectReadToken;
use PHPUnit\Framework\TestCase;

final class ProjectReadTokenEntityTest extends TestCase
{
    public function testAuditAndLifecycleAccessors(): void
    {
        $user = new User();
        $token = new ProjectReadToken();

        $token->setCreatedBy($user);
        self::assertSame($user, $token->getCreatedBy());
        self::assertNull($token->getRevokedAt());

        $token->revoke();
        self::assertFalse($token->isActive());

        $token->markUsed();
        self::assertGreaterThan(0, $token->getCreatedAt()->getTimestamp());
    }
}
