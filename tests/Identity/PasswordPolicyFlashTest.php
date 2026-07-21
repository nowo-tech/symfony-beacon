<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Shared\Menu\DashboardMenuDemoSeeder;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTime;
use Symfony\Component\HttpFoundation\Request;

/**
 * Password-policy expiry flashes must render as Beacon toasts (title + body).
 */
final class PasswordPolicyFlashTest extends DatabaseWebTestCase
{
    public function testExpiredPasswordShowsStructuredWarningToast(): void
    {
        [$client, $user] = $this->bootWithDemoProject('pwd-expiry-toast@example.com');
        $user->setPasswordChangedAt(new DateTime('-100 days'));
        self::getContainer()->get('doctrine')->getManager()->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.toast-stack .flash-warning[data-testid="flash-structured"]');
        self::assertSelectorExists('.toast-stack .flash-warning .flash__title');
        self::assertSelectorExists('.toast-stack .flash-warning .flash__message');
        self::assertSelectorTextContains('.toast-stack .flash-warning .flash__title', 'password has expired');
        self::assertSelectorTextContains('.toast-stack .flash-warning .flash__message', 'Account');
        self::assertSelectorTextContains('.toast-stack .flash-warning .flash__message', 'Security');
    }
}
