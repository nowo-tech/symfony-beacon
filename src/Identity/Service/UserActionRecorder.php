<?php

declare(strict_types=1);

namespace App\Identity\Service;

use Symfony\Component\HttpFoundation\Request;
use App\Identity\Entity\User;
use App\Identity\Entity\UserAction;
use App\Identity\UserActionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Persists administrative, membership, and product actions for the activity timeline.
 *
 * Callers must still {@see EntityManagerInterface::flush()} unless they use
 * {@see self::recordAndFlush()} (typical for navigation/view events with no other writes).
 */
final readonly class UserActionRecorder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * Create and persist a new activity row (not flushed).
     *
     * @param array<string, scalar|null> $context UI-facing metadata (names, roles, UUIDs, …)
     */
    public function record(
        UserActionType $action,
        ?User $actor,
        ?User $subjectUser = null,
        array $context = [],
    ): UserAction {
        $entry = new UserAction();
        $entry->setAction($action);
        $entry->setActor($actor);
        $entry->setSubjectUser($subjectUser);
        $entry->setContext($context);

        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof Request) {
            $entry->setIpAddress($request->getClientIp());
        }

        $this->entityManager->persist($entry);

        return $entry;
    }

    /**
     * Persist and flush a navigation/view activity row in one step.
     *
     * @param array<string, scalar|null> $context
     */
    public function recordAndFlush(
        UserActionType $action,
        ?User $actor,
        ?User $subjectUser = null,
        array $context = [],
    ): UserAction {
        $entry = $this->record($action, $actor, $subjectUser, $context);
        $this->entityManager->flush();

        return $entry;
    }
}
