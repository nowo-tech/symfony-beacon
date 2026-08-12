<?php

declare(strict_types=1);

namespace App\Notifications\Controller;

use App\Notifications\Service\HookDestinationContext;
use App\Issues\Enum\IssueStatus;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\IssueStatusChanger;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Service\ActionTokenConsumer;
use App\Notifications\Service\ActionTokenConsumeResult;
use App\Notifications\Service\HookDestinationContextResolver;
use App\Notifications\Service\HookMutationPolicy;
use App\Notifications\Service\InteractionActionToken;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Microsoft Teams MessageCard HttpPOST callbacks (Resolve).
 *
 * Public route; authorization is an HMAC token in the JSON body signed with the
 * destination signing secret. Anonymous Resolve is off by default
 * ({@see HookMutationPolicy}); prefer OpenUri Assign / a future authenticated Resolve.
 */
final class TeamsActionsController extends AbstractController
{
    public function __construct(
        private readonly InteractionActionToken $actionToken,
        private readonly HookDestinationContextResolver $destinationContextResolver,
        private readonly ActionTokenConsumer $actionTokenConsumer,
        private readonly IssueRepository $issueRepository,
        private readonly IssueStatusChanger $issueStatusChanger,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly HookMutationPolicy $hookMutationPolicy,
    ) {
    }

    #[Route('/hooks/teams/actions', name: 'hooks_teams_actions', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $rawBody = $request->getContent();
        if ('' === $rawBody) {
            return new Response('Missing body', Response::HTTP_BAD_REQUEST);
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($rawBody, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new Response('Invalid JSON', Response::HTTP_BAD_REQUEST);
        }

        $destinationUuid = isset($payload['d']) && \is_string($payload['d']) ? $payload['d'] : '';
        if ('' === $destinationUuid) {
            return new Response('Incomplete token', Response::HTTP_BAD_REQUEST);
        }

        $context = $this->destinationContextResolver->resolve($destinationUuid, NotificationDestinationType::Teams);
        if (!$context instanceof HookDestinationContext) {
            return new Response('Unknown destination', Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->actionToken->isValidResolveToken($context->signingSecret, $payload)) {
            $this->logger->warning('Teams action rejected: invalid token.', [
                'destination_uuid' => $destinationUuid,
            ]);

            return new Response('Invalid token', Response::HTTP_UNAUTHORIZED);
        }

        $consumeResult = $this->actionTokenConsumer->consumeOnce($payload);
        if (ActionTokenConsumeResult::AlreadyUsed === $consumeResult) {
            $this->logger->info('Teams action rejected: token already used.', [
                'destination_uuid' => $destinationUuid,
            ]);

            return new Response('Token already used', Response::HTTP_CONFLICT);
        }
        if (ActionTokenConsumeResult::Invalid === $consumeResult) {
            return new Response('Invalid token', Response::HTTP_UNAUTHORIZED);
        }

        $projectUuid = (string) $payload['p'];
        $issueUuid = (string) $payload['i'];

        if ($context->project->getUuid() !== $projectUuid) {
            return new Response('Project mismatch', Response::HTTP_FORBIDDEN);
        }

        $issue = $this->issueRepository->findOneBy(['uuid' => $issueUuid]);
        if (null === $issue || $issue->getProject()?->getUuid() !== $projectUuid) {
            return new Response('Issue not found', Response::HTTP_NOT_FOUND);
        }

        if (!$this->hookMutationPolicy->allowAnonymousResolve()) {
            $this->logger->info('Teams Resolve rejected: anonymous resolve disabled.', [
                'issue_uuid' => $issueUuid,
                'destination_uuid' => $destinationUuid,
            ]);

            return new Response(
                'Anonymous Teams Resolve is disabled; enable Allow anonymous Resolve under Administration → Ops defaults for legacy',
                Response::HTTP_FORBIDDEN,
            );
        }

        $changed = $this->issueStatusChanger->change($issue, IssueStatus::Resolved, null, 'teams');
        if (!$changed) {
            $this->entityManager->clear();
        }

        return new Response('', Response::HTTP_OK);
    }
}
