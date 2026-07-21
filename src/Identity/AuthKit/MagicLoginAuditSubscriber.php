<?php

declare(strict_types=1);

namespace App\Identity\AuthKit;

use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use Nowo\AuthKitBundle\MagicLogin\MagicLoginRequestedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** Records magic-login requests for the activity timeline. */
final class MagicLoginAuditSubscriber
{
    public function __construct(
        private readonly UserActionRecorder $userActionRecorder,
    ) {
    }

    #[AsEventListener(event: MagicLoginRequestedEvent::class)]
    public function onMagicLoginRequested(MagicLoginRequestedEvent $event): void
    {
        $context = $event->getContext();
        $this->userActionRecorder->recordAndFlush(UserActionType::MagicLoginRequested, null, null, [
            'email' => $context->identifier,
            'masked' => $context->maskedIdentifier,
        ]);
    }
}
