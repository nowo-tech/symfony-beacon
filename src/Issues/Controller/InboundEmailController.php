<?php

declare(strict_types=1);

namespace App\Issues\Controller;

use App\Issues\Service\InboundEmailCommentHandler;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Provider-agnostic inbound email webhook (Mailgun-style form fields).
 */
#[AsController]
final readonly class InboundEmailController
{
    public function __construct(
        private InboundEmailCommentHandler $handler,
        private LoggerInterface $logger,
        private InstanceOpsDefaults $opsDefaults,
    ) {
    }

    #[Route('/hooks/email/inbound', name: 'hooks_email_inbound', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $enabled = $this->opsDefaults->inboundEmailEnabled();
        $webhookSecret = $this->opsDefaults->inboundWebhookSecret();
        if (!$enabled || '' === $webhookSecret) {
            return new Response('Inbound email disabled', Response::HTTP_NOT_FOUND);
        }

        $provided = $request->headers->get('X-Beacon-Inbound-Secret');
        if (null === $provided || '' === $provided) {
            if ('' !== $request->request->getString('beacon_secret')) {
                $this->logger->warning('Inbound email webhook rejected: body beacon_secret is no longer accepted; use X-Beacon-Inbound-Secret.');
            }
            $this->logger->warning('Inbound email webhook rejected: missing X-Beacon-Inbound-Secret.');

            return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }
        if (!hash_equals($webhookSecret, $provided)) {
            $this->logger->warning('Inbound email webhook rejected: bad secret.');

            return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $from = $this->firstNonEmpty(
            $request->request->getString('sender'),
            $request->request->getString('from'),
            $request->headers->get('X-Beacon-Inbound-From', ''),
        );
        $recipient = $this->firstNonEmpty(
            $request->request->getString('recipient'),
            $request->request->getString('To'),
            $request->headers->get('X-Beacon-Inbound-To', ''),
        );
        $body = $this->firstNonEmpty(
            $request->request->getString('body-plain'),
            $request->request->getString('stripped-text'),
            $request->request->getString('text'),
            $request->getContent(),
        );
        $messageId = $this->firstNonEmpty(
            $request->request->getString('Message-Id'),
            $request->request->getString('message-id'),
            $request->headers->get('Message-Id', ''),
        );

        $fromEmail = $this->extractEmailAddress($from);
        if ('' === $fromEmail || '' === $recipient) {
            return new Response('Incomplete payload', Response::HTTP_BAD_REQUEST);
        }

        $result = $this->handler->handle($fromEmail, $recipient, $body, '' !== $messageId ? $messageId : null);

        return new Response($result, Response::HTTP_OK);
    }

    private function firstNonEmpty(string ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            if ('' !== trim($candidate)) {
                return trim($candidate);
            }
        }

        return '';
    }

    private function extractEmailAddress(string $from): string
    {
        if (preg_match('/<([^>]+)>/', $from, $m)) {
            return trim(strtolower($m[1]));
        }

        return trim(strtolower($from));
    }
}
