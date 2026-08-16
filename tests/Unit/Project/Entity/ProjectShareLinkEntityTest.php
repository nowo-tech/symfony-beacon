<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Entity;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectShareLink;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProjectShareLinkEntityTest extends TestCase
{
    public function testTracksUsageExpiryAndRevocation(): void
    {
        $project = new Project()->setName('Beacon')->setSlug('beacon');
        $issue = new Issue();
        $user = new User();
        $now = new DateTimeImmutable('2026-08-16 12:00:00');

        $link = new ProjectShareLink()
            ->setProject($project)
            ->setIssue($issue)
            ->setCreatedBy($user)
            ->setTokenHash('hash')
            ->setExpiresAt($now->modify('+1 day'))
            ->setMaxUses(2);

        self::assertSame($project, $link->getProject());
        self::assertSame($issue, $link->getIssue());
        self::assertSame($user, $link->getCreatedBy());
        self::assertSame('hash', $link->getTokenHash());
        self::assertFalse($link->isExpired($now));
        self::assertTrue($link->isUsable($now));

        $link->markUsed($now);
        self::assertSame(1, $link->getUseCount());
        self::assertSame($now, $link->getLastUsedAt());
        self::assertFalse($link->isExhausted());

        $link->markUsed($now->modify('+1 hour'));
        self::assertTrue($link->isExhausted());
        self::assertFalse($link->isUsable($now));

        $link->revoke($now->modify('+2 hours'));
        self::assertTrue($link->isRevoked());
        self::assertEquals($now->modify('+2 hours'), $link->getRevokedAt());
        self::assertFalse($link->isUsable($now));
        self::assertInstanceOf(DateTimeImmutable::class, $link->getCreatedAt());
    }
}
