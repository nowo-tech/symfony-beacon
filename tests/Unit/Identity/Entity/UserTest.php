<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Entity;

use App\Identity\Entity\InstanceRole;
use App\Identity\Entity\PasswordHistory;
use App\Identity\Entity\User;
use DateTime;
use DateTimeImmutable;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testNormalizesIdentityFieldsAndInitials(): void
    {
        $user = (new User())
            ->setEmail(' Person@example.com ')
            ->setDisplayName('Mary Jane')
            ->setSlackUserId(' U123 ')
            ->setPhone(' +34600111222 ')
            ->setPassword('hash')
            ->setPasswordResetToken('reset')
            ->setPasswordResetExpiresAt(new DateTimeImmutable('2026-08-16 12:00:00'))
            ->setPasswordChangedAt(new DateTime('2026-08-16 12:00:00'));

        self::assertSame('person@example.com', $user->getEmail());
        self::assertSame('Mary Jane', $user->getDisplayName());
        self::assertSame('U123', $user->getSlackUserId());
        self::assertSame('+34600111222', $user->getPhone());
        self::assertNull($user->getPhoneVerifiedAt());
        self::assertSame('MJ', $user->getInitials());
        self::assertSame('person@example.com', $user->getUserIdentifier());
        self::assertSame('hash', $user->getPassword());
        self::assertSame('reset', $user->getPasswordResetToken());
        self::assertInstanceOf(DateTimeImmutable::class, $user->getPasswordResetExpiresAt());
        self::assertInstanceOf(DateTime::class, $user->getPasswordChangedAt());

        $user->setDisplayName('Q');
        self::assertSame('Q', $user->getInitials());
        $user->setDisplayName('');
        self::assertSame('PE', $user->getInitials());
        $user->setSlackUserId('   ')->setPhone('   ');
        self::assertNull($user->getSlackUserId());
        self::assertNull($user->getPhone());
    }

    public function testTracksRolesPasswordHistoryPreferencesAndAuditUsers(): void
    {
        $user = new User();
        $enabledRole = (new InstanceRole())->setName('Support')->setCode('ROLE_SUPPORT');
        $disabledRole = (new InstanceRole())->setName('Dormant')->setCode('ROLE_DORMANT')->setEnabled(false);
        $history = (new PasswordHistory())->setPassword('old-hash');
        $actor = new User();
        $tourSeenAt = new DateTimeImmutable('2026-08-16 13:00:00');

        $user->setRoles(['ROLE_ADMIN']);
        $user->addInstanceRole($enabledRole)->addInstanceRole($disabledRole);
        self::assertCount(0, $user->getMemberships());
        self::assertTrue($user->hasInstanceRole($enabledRole));
        self::assertCount(2, $user->getInstanceRoles());
        self::assertContains('ROLE_SUPPORT', $user->getRoles());
        self::assertNotContains('ROLE_DORMANT', $user->getRoles());
        self::assertContains('ROLE_USER', $user->getRoles());

        $user->addPasswordHistory($history);
        self::assertCount(1, $user->getPasswordHistory());
        self::assertSame($user, $history->getUser());
        $user->removePasswordHistory($history);
        self::assertCount(0, $user->getPasswordHistory());

        $user
            ->setPreferredLocale('es')
            ->setPreferredTheme('dark')
            ->setPreferredContentWidth('full')
            ->setPreferredUiDensity('compact')
            ->setPreferredMotion('reduce')
            ->setPreferredFontScale('lg')
            ->setPreferredContrast('more')
            ->setPreferredSidebar('collapsed')
            ->setPreferredCollapsedIssuePanels(['raw', 'extra'])
            ->setPlainPassword('secret')
            ->markProductTourSeen($tourSeenAt)
            ->setAnonymizedAt(new DateTimeImmutable('2026-08-16 14:00:00'));
        $user->setCreatedBy($actor);
        $user->setUpdatedBy(new \stdClass());

        self::assertSame('es', $user->getPreferredLocale());
        self::assertSame('dark', $user->getPreferredTheme());
        self::assertSame(['raw', 'extra'], $user->getPreferredCollapsedIssuePanels());
        self::assertTrue($user->isProductTourSeen());
        self::assertSame($tourSeenAt, $user->getProductTourSeenAt());
        self::assertTrue($user->isAnonymized());
        self::assertSame($actor, $user->getCreatedBy());
        self::assertNull($user->getUpdatedBy());

        $user->eraseCredentials();
        self::assertNull($user->getPlainPassword());
        $user->removeInstanceRole($enabledRole);
        self::assertFalse($user->hasInstanceRole($enabledRole));

        $this->expectException(\InvalidArgumentException::class);
        $user->addPasswordHistory($this->createStub(PasswordHistoryInterface::class));
    }
}
