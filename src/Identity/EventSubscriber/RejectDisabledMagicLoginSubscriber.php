<?php

declare(strict_types=1);

namespace App\Identity\EventSubscriber;

use App\Identity\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * Ensures disabled accounts cannot authenticate via login_link (magic login).
 */
final class RejectDisabledMagicLoginSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // After UserBadge resolution / alongside UserCheckerListener (-10).
        return [CheckPassportEvent::class => ['checkPassport', -5]];
    }

    public function checkPassport(CheckPassportEvent $event): void
    {
        $user = $event->getPassport()->getUser();
        if ($user instanceof User && !$user->isEnabled()) {
            throw new CustomUserMessageAccountStatusException('Account is disabled.');
        }
    }
}
