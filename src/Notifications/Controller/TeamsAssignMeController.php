<?php

declare(strict_types=1);

namespace App\Notifications\Controller;

use App\Identity\Entity\User;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\IssueAssigneeChanger;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Service\ActionTokenConsumer;
use App\Notifications\Service\ActionTokenConsumeResult;
use App\Notifications\Service\HookDestinationContextResolver;
use App\Notifications\Service\InteractionActionToken;
use App\Project\Service\ProjectAccessService;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        private readonly HookDestinationContextResolver $destinationContextResolver,
        private readonly ActionTokenConsumer $actionTokenConsumer,
        private readonly IssueRepository $issueRepository,
        private readonly IssueAssigneeChanger $issueAssigneeChanger,
        private readonly ProjectAccessService $projectAccess,
        private readonly LoggerInterface $logger,
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

        $context = $this->destinationContextResolver->resolve($destinationUuid, NotificationDestinationType::Teams);
        if (null === $context) {
            throw $this->createAccessDeniedException('Unknown destination');
        }

        if (!$this->actionToken->isValidAssignToken($context->signingSecret, $payload)) {
            $this->logger->warning('Teams Assign rejected: invalid token.', [
                'destination_uuid' => $destinationUuid,
            ]);

            throw $this->createAccessDeniedException('Invalid token');
        }

        $consumeResult = $this->actionTokenConsumer->consumeOnce($payload);
        if (ActionTokenConsumeResult::AlreadyUsed === $consumeResult) {
            $this->logger->info('Teams Assign rejected: token already used.', [
                'destination_uuid' => $destinationUuid,
            ]);
            $this->addFlash('error', 'notifications.teams.token_used');

            return $this->redirectToRoute('issue_show', [
                'projectId' => $payload['p'],
                'id' => $payload['i'],
            ]);
        }
        if (ActionTokenConsumeResult::Invalid === $consumeResult) {
            throw $this->createAccessDeniedException('Invalid token');
        }

        $projectUuid = $payload['p'];
        $issueUuid = $payload['i'];

        if ($context->project->getUuid() !== $projectUuid) {
            throw $this->createAccessDeniedException('Project mismatch');
        }

        $issue = $this->issueRepository->findOneBy(['uuid' => $issueUuid]);
        if (null === $issue || $issue->getProject()?->getUuid() !== $projectUuid) {
            throw $this->createNotFoundException('Issue not found');
        }

        try {
            $this->projectAccess->requireTriage($context->project, $user);
        } catch (AccessDeniedHttpException) {
            $this->addFlash('error', 'issues.assignee_forbidden');

            return $this->redirectToRoute('issue_show', [
                'projectId' => $context->project->getUuid(),
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
            'projectId' => $context->project->getUuid(),
            'id' => $issue->getUuid(),
        ]);
    }
}
