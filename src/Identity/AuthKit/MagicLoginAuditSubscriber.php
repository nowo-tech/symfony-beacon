<?php

declare(strict_types=1);

namespace App\Identity\AuthKit;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use Nowo\AuthKitBundle\MagicLogin\MagicLoginRequestedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** Records magic-login requests for the activity timeline. */
final readonly class MagicLoginAuditSubscriber
{
    public function __construct(
        private UserActionRecorder $userActionRecorder,
        private UserRepository $userRepository,
    ) {
    }

    #[AsEventListener(event: MagicLoginRequestedEvent::class)]
    public function onMagicLoginRequested(MagicLoginRequestedEvent $event): void
    {
        $context = $event->getContext();
        $subject = $this->userRepository->findOneByEmail(strtolower(trim($context->identifier)));
        $this->userActionRecorder->recordAndFlush(
            UserActionType::MagicLoginRequested,
            null,
            $subject instanceof User ? $subject : null,
            [
                'email' => $context->identifier,
                'masked' => $context->maskedIdentifier,
            ],
        );
    }
}
