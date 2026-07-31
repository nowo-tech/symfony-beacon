<?php

declare(strict_types=1);

namespace App\Identity\AuthKit;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use Nowo\AuthKitBundle\PasswordReset\PasswordResetRequestedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** Records password-reset requests for the activity timeline. */
final readonly class PasswordResetAuditSubscriber
{
    public function __construct(
        private UserActionRecorder $userActionRecorder,
        private UserRepository $userRepository,
    ) {
    }

    #[AsEventListener(event: PasswordResetRequestedEvent::class)]
    public function onPasswordResetRequested(PasswordResetRequestedEvent $event): void
    {
        $context = $event->getContext();
        $subject = $this->userRepository->findOneByEmail(strtolower(trim($context->identifier)));
        $this->userActionRecorder->recordAndFlush(
            UserActionType::PasswordResetRequested,
            null,
            $subject instanceof User ? $subject : null,
            [
                'email' => $context->identifier,
                'masked' => $context->maskedIdentifier,
                'delivery' => $context->deliveryMode->value,
            ],
        );
    }
}
