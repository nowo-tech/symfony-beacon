<?php

declare(strict_types=1);

namespace App\Notifications\Controller;

use App\Identity\Entity\User;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\IssueAssigneeChanger;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Service\InteractionActionToken;
use App\Project\Entity\Project;
use App\Project\Service\ProjectAccessService;
use InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Microsoft Teams MessageCard OpenUri callback for Assign to me.
 *
 * Requires a Beacon session (ROLE_USER). Authorization is an HMAC token in the
 * query string signed with the destination signing secret, plus project triage.
 */
#[IsGranted('ROLE_USER')]
final class TeamsAssignMeController extends AbstractController
{
    public function __construct(
        private readonly InteractionActionToken $actionToken,
        private readonly NotificationDestinationRepository $destinationRepository,
        private readonly IssueRepository $issueRepository,
        private readonly IssueAssigneeChanger $issueAssigneeChanger,
        private readonly ProjectAccessService $projectAccess,
        private readonly LoggerInterface $logger,
        #[Autowire(service: 'cache.action_token')]
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    #[Route('/hooks/teams/assign-me', name: 'hooks_teams_assign_me', methods: ['GET'])]
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $payload = [
            'a' => $request->query->getString('a'),
            'd' => $request->query->getString('d'),
            'p' => $request->query->getString('p'),
            'i' => $request->query->getString('i'),
            'n' => $request->query->getString('n'),
            'exp' => $request->query->get('exp'),
            'sig' => $request->query->getString('sig'),
        ];

        $destinationUuid = $payload['d'];
        if ('' === $destinationUuid) {
            throw $this->createAccessDeniedException('Incomplete token');
        }

        $destination = $this->destinationRepository->findOneBy(['uuid' => $destinationUuid]);
        if (!$destination instanceof NotificationDestination
            || NotificationDestinationType::Teams !== $destination->getType()
            || !$destination->hasSigningSecret()
        ) {
            throw $this->createAccessDeniedException('Unknown destination');
        }

        $secret = (string) $destination->getSigningSecret();
        if (!$this->actionToken->isValidAssignToken($secret, $payload)) {
            $this->logger->warning('Teams Assign rejected: invalid token.', [
                'destination_uuid' => $destinationUuid,
            ]);

            throw $this->createAccessDeniedException('Invalid token');
        }

        $cacheKey = $this->actionToken->consumeCacheKey($payload);
        if (null === $cacheKey) {
            throw $this->createAccessDeniedException('Invalid token');
        }
        $item = $this->cache->getItem($cacheKey);
        if ($item->isHit()) {
            $this->logger->info('Teams Assign rejected: token already used.', [
                'destination_uuid' => $destinationUuid,
            ]);
            $this->addFlash('error', 'notifications.teams.token_used');

            $projectUuid = $payload['p'];
            $issueUuid = $payload['i'];

            return $this->redirectToRoute('issue_show', [
                'projectId' => $projectUuid,
                'id' => $issueUuid,
            ]);
        }
        $item->set(1);
        $ttl = max(1, (int) $payload['exp'] - time());
        $item->expiresAfter($ttl);
        $this->cache->save($item);

        $projectUuid = $payload['p'];
        $issueUuid = $payload['i'];

        $project = $destination->getProject();
        if (!$project instanceof Project || $project->getUuid() !== $projectUuid) {
            throw $this->createAccessDeniedException('Project mismatch');
        }

        $issue = $this->issueRepository->findOneBy(['uuid' => $issueUuid]);
        if (null === $issue || $issue->getProject()?->getUuid() !== $projectUuid) {
            throw $this->createNotFoundException('Issue not found');
        }

        try {
            $this->projectAccess->requireTriage($project, $user);
        } catch (AccessDeniedHttpException) {
            $this->addFlash('error', 'issues.assignee_forbidden');

            return $this->redirectToRoute('issue_show', [
                'projectId' => $project->getUuid(),
                'id' => $issue->getUuid(),
            ]);
        }

        try {
            if ($this->issueAssigneeChanger->assign($issue, $user, $user, 'teams')) {
                $this->addFlash('success', 'issues.assignee_saved');
            }
        } catch (InvalidArgumentException) {
            $this->addFlash('error', 'issues.assignee_not_member');
        }

        return $this->redirectToRoute('issue_show', [
            'projectId' => $project->getUuid(),
            'id' => $issue->getUuid(),
        ]);
    }
}
