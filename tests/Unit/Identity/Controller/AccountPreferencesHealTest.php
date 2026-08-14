<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Identity\Controller\AccountPreferencesController;
use App\Identity\Entity\User;
use App\Identity\UserDisplayPreferenceDefaults;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

final class AccountPreferencesHealTest extends TestCase
{
    public function testHealSkipsAnonymizedUsers(): void
    {
        $user = (new User())->setEmail('gone@example.com')->setAnonymizedAt(new DateTimeImmutable());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $controller = new ReflectionClass(AccountPreferencesController::class)->newInstanceWithoutConstructor();
        (new ReflectionProperty(AccountPreferencesController::class, 'entityManager'))->setValue($controller, $em);

        $method = new ReflectionMethod(AccountPreferencesController::class, 'healDisplayPreferencesIfNeeded');
        $method->invoke($controller, $user);
    }

    public function testHealAppliesDefaultsWhenRawPreferencesNull(): void
    {
        $user = (new User())->setEmail('prefs@example.com');
        self::assertNull($user->getPreferredLocaleRaw());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $controller = new ReflectionClass(AccountPreferencesController::class)->newInstanceWithoutConstructor();
        (new ReflectionProperty(AccountPreferencesController::class, 'entityManager'))->setValue($controller, $em);
        $container = new Container();
        $container->set('parameter_bag', new ParameterBag(['default_locale' => 'en']));
        $controller->setContainer($container);

        $method = new ReflectionMethod(AccountPreferencesController::class, 'healDisplayPreferencesIfNeeded');
        $method->invoke($controller, $user);

        self::assertSame('en', $user->getPreferredLocale());
        self::assertNotNull($user->getPreferredThemeRaw());
    }

    public function testHealNoopsWhenAllPreferencesSet(): void
    {
        $user = (new User())->setEmail('ok@example.com');
        UserDisplayPreferenceDefaults::applyMissing($user, 'en');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $controller = new ReflectionClass(AccountPreferencesController::class)->newInstanceWithoutConstructor();
        (new ReflectionProperty(AccountPreferencesController::class, 'entityManager'))->setValue($controller, $em);

        $method = new ReflectionMethod(AccountPreferencesController::class, 'healDisplayPreferencesIfNeeded');
        $method->invoke($controller, $user);
    }
}
