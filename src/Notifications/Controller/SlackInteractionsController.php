<?php

declare(strict_types=1);

namespace App\Notifications\Controller;

use App\Notifications\Service\HookDestinationContext;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueStatus;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\IssueAssigneeChanger;
use App\Issues\Service\IssueStatusChanger;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Service\HookDestinationContextResolver;
use App\Notifications\Service\HookMutationPolicy;
use App\Notifications\Service\SlackRequestSignatureVerifier;
use App\Project\Access\ProjectAccess;
use App\Project\Service\ProjectAccessService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Slack interactive component callbacks (Block Kit Resolve / Assign to me).
 *
 * Public route; authorization is HMAC signature verification against the
 * destination signing secret. Resolve and Assign require a mapped Slack user id
 * with triage unless {@see HookMutationPolicy::allowAnonymousResolve()} is enabled.
 */
final class SlackInteractionsController extends AbstractController
{
    public function __construct(
        private readonly SlackRequestSignatureVerifier $signatureVerifier,
        private readonly HookDestinationContextResolver $destinationContextResolver,
        private readonly IssueRepository $issueRepository,
        private readonly UserRepository $userRepository,
        private readonly IssueStatusChanger $issueStatusChanger,
        private readonly IssueAssigneeChanger $issueAssigneeChanger,
        private readonly ProjectAccessService $projectAccess,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly HookMutationPolicy $hookMutationPolicy,
    ) {
    }

    #[Route('/hooks/slack/interactions', name: 'hooks_slack_interactions', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $rawBody = $request->getContent();
        $payloadRaw = $request->request->getString('payload');
        if ('' === $payloadRaw && '' !== $rawBody) {
            parse_str($rawBody, $parsed);
            $payloadRaw = isset($parsed['payload']) && \is_string($parsed['payload']) ? $parsed['payload'] : '';
        }
        if ('' === $payloadRaw) {
            return new Response('Missing payload', Response::HTTP_BAD_REQUEST);
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($payloadRaw, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new Response('Invalid payload', Response::HTTP_BAD_REQUEST);
        }

        // Events API url_verification does not belong on the interactions endpoint.
        // Never echo a challenge without HMAC (open reflector). Use a dedicated Events route if needed.
        if (isset($payload['type']) && 'url_verification' === $payload['type']) {
            return new Response('Unsupported on interactions endpoint', Response::HTTP_BAD_REQUEST);
        }

        $actions = $payload['actions'] ?? null;
        if (!\is_array($actions) || [] === $actions || !\is_array($actions[0] ?? null)) {
            return new Response('No actions', Response::HTTP_BAD_REQUEST);
        }

        /** @var array<string, mixed> $action */
        $action = $actions[0];
        $valueRaw = isset($action['value']) && \is_string($action['value']) ? $action['value'] : '';
        if ('' === $valueRaw) {
            return new Response('Missing action value', Response::HTTP_BAD_REQUEST);
        }

        try {
            /** @var array<string, mixed> $value */
            $value = json_decode($valueRaw, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new Response('Invalid action value', Response::HTTP_BAD_REQUEST);
        }

        $actionName = $value['a'] ?? null;
        if (!\in_array($actionName, ['resolve', 'assign'], true)) {
            return new Response('Unsupported action', Response::HTTP_BAD_REQUEST);
        }

        $destinationUuid = isset($value['d']) && \is_string($value['d']) ? $value['d'] : '';
        $projectUuid = isset($value['p']) && \is_string($value['p']) ? $value['p'] : '';
        $issueUuid = isset($value['i']) && \is_string($value['i']) ? $value['i'] : '';
        if (\in_array('', [$destinationUuid, $projectUuid, $issueUuid], true)) {
            return new Response('Incomplete action value', Response::HTTP_BAD_REQUEST);
        }

        $context = $this->destinationContextResolver->resolve(
            $destinationUuid,
            NotificationDestinationType::Slack,
        );
        if (!$context instanceof HookDestinationContext) {
            return new Response('Unknown destination', Response::HTTP_UNAUTHORIZED);
        }

        $secret = $context->signingSecret;
        $timestamp = $request->headers->get('X-Slack-Request-Timestamp', '');
        $signature = $request->headers->get('X-Slack-Signature', '');
        if (!$this->signatureVerifier->isValid($secret, $timestamp, $signature, $rawBody)) {
            $this->logger->warning('Slack interaction rejected: invalid signature.', [
                'destination_uuid' => $destinationUuid,
            ]);

            return new Response('Invalid signature', Response::HTTP_UNAUTHORIZED);
        }

        $project = $context->project;
        if ($project->getUuid() !== $projectUuid) {
            return new Response('Project mismatch', Response::HTTP_FORBIDDEN);
        }

        $issue = $this->issueRepository->findOneBy(['uuid' => $issueUuid]);
        if (!$issue instanceof Issue || $issue->getProject()?->getUuid() !== $projectUuid) {
            return new Response('Issue not found', Response::HTTP_NOT_FOUND);
        }

        $slackUserId = $this->extractSlackUserId($payload);
        $mappedUser = null !== $slackUserId && '' !== $slackUserId
            ? $this->userRepository->findOneBySlackUserId($slackUserId)
            : null;

        if ('resolve' === $actionName) {
            $actor = null;
            if ($mappedUser instanceof User) {
                $access = $this->projectAccess->resolveAccess($project, $mappedUser);
                if ($access instanceof ProjectAccess && $access->canTriageIssues()) {
                    $actor = $mappedUser;
                }
            }
            if (!$actor instanceof User && !$this->hookMutationPolicy->allowAnonymousResolve()) {
                $this->logger->info('Slack Resolve rejected: mapped triage user required.', [
                    'slack_user_id' => $slackUserId,
                    'issue_uuid' => $issueUuid,
                ]);

                return new Response(
                    'Link your Slack user id under Account → Profile (triage required)',
                    Response::HTTP_FORBIDDEN,
                );
            }
            $changed = $this->issueStatusChanger->change($issue, IssueStatus::Resolved, $actor, 'slack');
            if (!$changed) {
                $this->entityManager->clear();
            }

            return new Response('', Response::HTTP_OK);
        }

        // assign
        if (!$mappedUser instanceof User) {
            $this->logger->info('Slack Assign rejected: Slack user id not linked in Beacon.', [
                'slack_user_id' => $slackUserId,
                'issue_uuid' => $issueUuid,
            ]);

            return new Response('Link your Slack user id under Account → Profile', Response::HTTP_FORBIDDEN);
        }

        try {
            $this->projectAccess->requireTriage($project, $mappedUser);
        } catch (AccessDeniedHttpException) {
            return new Response('Triage permission required', Response::HTTP_FORBIDDEN);
        }

        try {
            $changed = $this->issueAssigneeChanger->assign($issue, $mappedUser, $mappedUser, 'slack');
        } catch (InvalidArgumentException) {
            return new Response('Assignee must be a project member', Response::HTTP_FORBIDDEN);
        }

        if (!$changed) {
            $this->entityManager->clear();
        }

        return new Response('', Response::HTTP_OK);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractSlackUserId(array $payload): ?string
    {
        $user = $payload['user'] ?? null;
        if (!\is_array($user)) {
            return null;
        }
        $id = $user['id'] ?? null;

        return \is_string($id) && '' !== $id ? $id : null;
    }
}
