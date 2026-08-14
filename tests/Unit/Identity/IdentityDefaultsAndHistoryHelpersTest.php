<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\Controller\AccountPreferencesController;
use App\Identity\Controller\AdminGroupController;
use App\Identity\Entity\PasswordHistory;
use App\Identity\Entity\User;
use App\Identity\UserActionType;
use App\Identity\UserDisplayPreferenceDefaults;
use DateTime;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class IdentityDefaultsAndHistoryHelpersTest extends TestCase
{
    public function testUserDisplayPreferenceDefaultsFillsNullsAndKeepsExisting(): void
    {
        $user = new User()->setEmail('prefs@example.com');
        $user->setPreferredTheme('dark');

        UserDisplayPreferenceDefaults::applyMissing($user, '  ES  ');

        self::assertSame('es', $user->getPreferredLocale());
        self::assertSame('dark', $user->getPreferredTheme());
        self::assertSame(UserDisplayPreferenceDefaults::MOTION, $user->getPreferredMotion());
        self::assertSame(UserDisplayPreferenceDefaults::SIDEBAR, $user->getPreferredSidebar());
    }

    public function testUserDisplayPreferenceDefaultsFallsBackForBlankLocale(): void
    {
        $user = new User()->setEmail('blank@example.com');
        UserDisplayPreferenceDefaults::applyMissing($user, '   ');

        self::assertSame(UserDisplayPreferenceDefaults::LOCALE, $user->getPreferredLocale());
    }

    public function testPasswordChangeHistoryForSortsNewestFirst(): void
    {
        $user = new User()->setEmail('pwd@example.com');
        $older = new PasswordHistory()->setUser($user)->setPassword('hash-old')->setCreatedAt(new DateTime('2026-01-01 10:00:00'));
        $newer = new PasswordHistory()->setUser($user)->setPassword('hash-new')->setCreatedAt(new DateTime('2026-02-01 10:00:00'));
        $user->addPasswordHistory($older);
        $user->addPasswordHistory($newer);

        $controller = new ReflectionClass(AccountPreferencesController::class)->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AccountPreferencesController::class, 'passwordChangeHistoryFor');
        $dates = $method->invoke($controller, $user);

        self::assertCount(2, $dates);
        self::assertSame($newer->getCreatedAt(), $dates[0]);
        self::assertSame($older->getCreatedAt(), $dates[1]);
    }

    public function testAuditActionChoicesMapsTranslationKeys(): void
    {
        $controller = new ReflectionClass(AdminGroupController::class)->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AdminGroupController::class, 'auditActionChoices');
        $choices = $method->invoke($controller, [UserActionType::GroupCreated, UserActionType::GroupUpdated]);

        self::assertSame([
            'users.activity.action.group.created' => 'group.created',
            'users.activity.action.group.updated' => 'group.updated',
        ], $choices);
    }
}
