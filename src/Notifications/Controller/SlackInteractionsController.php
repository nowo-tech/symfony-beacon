<?php

declare(strict_types=1);

namespace App\Notifications\Controller;

use App\Issues\Repository\IssueRepository;
use App\Issues\Service\IssueStatusChanger;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Service\SlackRequestSignatureVerifier;
use App\Shared\IssueStatus;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Slack interactive component callbacks (Block Kit buttons).
 *
 * Public route; authorization is HMAC signature verification against the
 * destination signing secret. Actor is null in v1 (Slack→member mapping Later).
 */
final class SlackInteractionsController extends AbstractController
{
    public function __construct(
        private readonly SlackRequestSignatureVerifier $signatureVerifier,
        private readonly NotificationDestinationRepository $destinationRepository,
        private readonly IssueRepository $issueRepository,
        private readonly IssueStatusChanger $issueStatusChanger,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
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

        // Slack URL verification challenge (rare for interactivity; keep harmless).
        if (isset($payload['type']) && 'url_verification' === $payload['type'] && isset($payload['challenge'])) {
            return new Response((string) $payload['challenge'], Response::HTTP_OK, ['Content-Type' => 'text/plain']);
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

        if (($value['a'] ?? null) !== 'resolve') {
            return new Response('Unsupported action', Response::HTTP_BAD_REQUEST);
        }

        $destinationUuid = isset($value['d']) && \is_string($value['d']) ? $value['d'] : '';
        $projectUuid = isset($value['p']) && \is_string($value['p']) ? $value['p'] : '';
        $issueUuid = isset($value['i']) && \is_string($value['i']) ? $value['i'] : '';
        if ('' === $destinationUuid || '' === $projectUuid || '' === $issueUuid) {
            return new Response('Incomplete action value', Response::HTTP_BAD_REQUEST);
        }

        $destination = $this->destinationRepository->findOneBy(['uuid' => $destinationUuid]);
        if (!$destination instanceof NotificationDestination
            || NotificationDestinationType::Slack !== $destination->getType()
            || !$destination->hasSigningSecret()
        ) {
            return new Response('Unknown destination', Response::HTTP_UNAUTHORIZED);
        }

        $secret = (string) $destination->getSigningSecret();
        $timestamp = $request->headers->get('X-Slack-Request-Timestamp', '');
        $signature = $request->headers->get('X-Slack-Signature', '');
        if (!$this->signatureVerifier->isValid($secret, $timestamp, $signature, $rawBody)) {
            $this->logger->warning('Slack interaction rejected: invalid signature.', [
                'destination_uuid' => $destinationUuid,
            ]);

            return new Response('Invalid signature', Response::HTTP_UNAUTHORIZED);
        }

        $project = $destination->getProject();
        if (null === $project || $project->getUuid() !== $projectUuid) {
            return new Response('Project mismatch', Response::HTTP_FORBIDDEN);
        }

        $issue = $this->issueRepository->findOneBy(['uuid' => $issueUuid]);
        if (null === $issue || $issue->getProject()?->getUuid() !== $projectUuid) {
            return new Response('Issue not found', Response::HTTP_NOT_FOUND);
        }

        $changed = $this->issueStatusChanger->change($issue, IssueStatus::Resolved, null);
        // Ensure any no-op still clears EM state cleanly (changer flushes on change).
        if (!$changed) {
            $this->entityManager->clear();
        }

        // Ephemeral-style ack for Slack (empty 200 is fine for button clicks).
        return new Response('', Response::HTTP_OK);
    }
}
