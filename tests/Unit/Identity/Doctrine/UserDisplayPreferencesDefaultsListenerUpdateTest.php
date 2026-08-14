<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Doctrine;

use App\Identity\Doctrine\UserDisplayPreferencesDefaultsListener;
use App\Identity\Entity\User;
use App\Identity\UserDisplayPreferenceDefaults;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use stdClass;

final class UserDisplayPreferencesDefaultsListenerUpdateTest extends TestCase
{
    public function testPreUpdateSkipsWhenPreferencesAlreadySet(): void
    {
        $user = new User();
        UserDisplayPreferenceDefaults::applyMissing($user, 'en');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('getUnitOfWork');

        $changeSet = [];
        new UserDisplayPreferencesDefaultsListener('en')->preUpdate(new PreUpdateEventArgs($user, $em, $changeSet));
    }

    public function testPreUpdateAppliesMissingAndRecomputesChangeSet(): void
    {
        $user = new User();
        self::assertNull($user->getPreferredLocaleRaw());

        $meta = $this->createStub(ClassMetadata::class);
        $uow = $this->createMock(UnitOfWork::class);
        $uow->expects(self::once())->method('recomputeSingleEntityChangeSet')->with($meta, $user);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);
        $em->method('getClassMetadata')->willReturn($meta);

        $changeSet = [];
        new UserDisplayPreferencesDefaultsListener('de')->preUpdate(new PreUpdateEventArgs($user, $em, $changeSet));

        self::assertSame('de', $user->getPreferredLocaleRaw());
        self::assertSame(UserDisplayPreferenceDefaults::THEME, $user->getPreferredThemeRaw());
    }

    public function testPreUpdateSkipsAnonymizedUser(): void
    {
        $user = new User();
        $user->setAnonymizedAt(new DateTimeImmutable());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('getUnitOfWork');
        $changeSet = [];
        new UserDisplayPreferencesDefaultsListener('en')->preUpdate(new PreUpdateEventArgs($user, $em, $changeSet));
        self::assertNull($user->getPreferredLocaleRaw());
    }

    public function testPrePersistStillCoveredForNonUser(): void
    {
        $args = new PrePersistEventArgs(new stdClass(), $this->createStub(EntityManagerInterface::class));
        new UserDisplayPreferencesDefaultsListener('en')->prePersist($args);
        $this->addToAssertionCount(1);
    }
}
