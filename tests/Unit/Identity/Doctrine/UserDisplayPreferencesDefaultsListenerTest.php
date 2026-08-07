<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Doctrine;

use App\Identity\Doctrine\UserDisplayPreferencesDefaultsListener;
use App\Identity\Entity\User;
use App\Identity\UserDisplayPreferenceDefaults;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use PHPUnit\Framework\TestCase;

final class UserDisplayPreferencesDefaultsListenerTest extends TestCase
{
    public function testPrePersistSkipsNonUser(): void
    {
        $entity = new class {
            public string $name = 'not-a-user';
        };

        $args = new PrePersistEventArgs($entity, $this->createStub(EntityManagerInterface::class));
        (new UserDisplayPreferencesDefaultsListener('en'))->prePersist($args);

        $this->addToAssertionCount(1);
    }

    public function testPrePersistSkipsAnonymizedUser(): void
    {
        $user = new User();
        $user->setAnonymizedAt(new DateTimeImmutable());
        self::assertNull($user->getPreferredLocaleRaw());

        $args = new PrePersistEventArgs($user, $this->createStub(EntityManagerInterface::class));
        (new UserDisplayPreferencesDefaultsListener('es'))->prePersist($args);

        self::assertNull($user->getPreferredLocaleRaw());
    }

    public function testPrePersistAppliesMissingDefaults(): void
    {
        $user = new User();
        self::assertNull($user->getPreferredLocaleRaw());

        $args = new PrePersistEventArgs($user, $this->createStub(EntityManagerInterface::class));
        (new UserDisplayPreferencesDefaultsListener('es'))->prePersist($args);

        self::assertSame('es', $user->getPreferredLocaleRaw());
        self::assertSame(UserDisplayPreferenceDefaults::THEME, $user->getPreferredThemeRaw());
        self::assertSame(UserDisplayPreferenceDefaults::MOTION, $user->getPreferredMotionRaw());
        self::assertSame(UserDisplayPreferenceDefaults::CONTRAST, $user->getPreferredContrastRaw());
        self::assertSame(UserDisplayPreferenceDefaults::CONTENT_WIDTH, $user->getPreferredContentWidthRaw());
        self::assertSame(UserDisplayPreferenceDefaults::UI_DENSITY, $user->getPreferredUiDensityRaw());
        self::assertSame(UserDisplayPreferenceDefaults::FONT_SCALE, $user->getPreferredFontScaleRaw());
        self::assertSame(UserDisplayPreferenceDefaults::SIDEBAR, $user->getPreferredSidebarRaw());
    }
}
