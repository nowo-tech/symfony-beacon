<?php

declare(strict_types=1);

namespace App\Notifications\Controller;

use App\Issues\Repository\IssueRepository;
use App\Issues\Service\IssueStatusChanger;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Service\HookMutationPolicy;
use App\Notifications\Service\InteractionActionToken;
use App\Project\Entity\Project;
use App\Issues\Enum\IssueStatus;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        private readonly NotificationDestinationRepository $destinationRepository,
        private readonly IssueRepository $issueRepository,
        private readonly IssueStatusChanger $issueStatusChanger,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly HookMutationPolicy $hookMutationPolicy,
        #[Autowire(service: 'cache.action_token')]
        private readonly CacheItemPoolInterface $cache,
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

        $destination = $this->destinationRepository->findOneBy(['uuid' => $destinationUuid]);
        if (!$destination instanceof NotificationDestination
            || NotificationDestinationType::Teams !== $destination->getType()
            || !$destination->hasSigningSecret()
        ) {
            return new Response('Unknown destination', Response::HTTP_UNAUTHORIZED);
        }

        $secret = (string) $destination->getSigningSecret();
        if (!$this->actionToken->isValidResolveToken($secret, $payload)) {
            $this->logger->warning('Teams action rejected: invalid token.', [
                'destination_uuid' => $destinationUuid,
            ]);

            return new Response('Invalid token', Response::HTTP_UNAUTHORIZED);
        }

        $cacheKey = $this->actionToken->consumeCacheKey($payload);
        if (null === $cacheKey) {
            return new Response('Invalid token', Response::HTTP_UNAUTHORIZED);
        }
        $item = $this->cache->getItem($cacheKey);
        if ($item->isHit()) {
            $this->logger->info('Teams action rejected: token already used.', [
                'destination_uuid' => $destinationUuid,
            ]);

            return new Response('Token already used', Response::HTTP_CONFLICT);
        }
        $item->set(1);
        $ttl = max(1, (int) $payload['exp'] - time());
        $item->expiresAfter($ttl);
        $this->cache->save($item);

        $projectUuid = (string) $payload['p'];
        $issueUuid = (string) $payload['i'];

        $project = $destination->getProject();
        if (!$project instanceof Project || $project->getUuid() !== $projectUuid) {
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
