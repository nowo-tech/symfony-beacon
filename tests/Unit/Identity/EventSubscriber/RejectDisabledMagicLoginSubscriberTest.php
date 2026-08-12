<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\EventSubscriber;

use App\Identity\Entity\User;
use App\Identity\EventSubscriber\RejectDisabledMagicLoginSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

final class RejectDisabledMagicLoginSubscriberTest extends TestCase
{
    public function testSubscribesToCheckPassport(): void
    {
        self::assertSame(
            [CheckPassportEvent::class => ['checkPassport', -5]],
            RejectDisabledMagicLoginSubscriber::getSubscribedEvents(),
        );
    }

    public function testRejectsDisabledBeaconUser(): void
    {
        $user = new User();
        $user->setEmail('disabled@example.com');
        $user->setEnabled(false);

        $event = $this->eventFor($user);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Account is disabled.');

        new RejectDisabledMagicLoginSubscriber()->checkPassport($event);
    }

    public function testAllowsEnabledBeaconUser(): void
    {
        $user = new User();
        $user->setEmail('ok@example.com');
        $user->setEnabled(true);

        new RejectDisabledMagicLoginSubscriber()->checkPassport($this->eventFor($user));

        $this->addToAssertionCount(1);
    }

    public function testIgnoresNonBeaconUser(): void
    {
        $user = new InMemoryUser('guest', null);

        new RejectDisabledMagicLoginSubscriber()->checkPassport($this->eventFor($user));

        $this->addToAssertionCount(1);
    }

    private function eventFor(object $user): CheckPassportEvent
    {
        $identifier = method_exists($user, 'getUserIdentifier')
            ? $user->getUserIdentifier()
            : 'user';

        $passport = new SelfValidatingPassport(
            new UserBadge($identifier, static fn (): object => $user),
        );

        return new CheckPassportEvent(
            $this->createStub(AuthenticatorInterface::class),
            $passport,
        );
    }
}
