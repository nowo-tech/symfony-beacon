<?php

declare(strict_types=1);

namespace App\Ingest\Otlp\Service;

use App\Ingest\Message\ProcessEnvelopeMessage;
use App\Ingest\Service\IngestProjectAccessGate;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Shared post-accept OTLP flow: map → empty ACK → envelope → async dispatch.
 */
final readonly class OtlpIngestPipeline
{
    public function __construct(
        private OtlpIngestGatewayInterface $otlpIngestGateway,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    public function ingest(
        string $projectRef,
        Request $request,
        OtlpSignalMapperInterface $mapper,
        string $signalLabel,
    ): Response {
        $accepted = $this->otlpIngestGateway->accept($projectRef, $request);
        if ($accepted instanceof Response) {
            return $accepted;
        }

        $projectId = $accepted['project']->getId();
        if (null === $projectId) {
            return $this->otlpIngestGateway->respond(
                IngestProjectAccessGate::UNAUTHORIZED_MESSAGE,
                Response::HTTP_UNAUTHORIZED,
            );
        }

        try {
            $payloads = $mapper->mapToEventPayloads($accepted['body']);
        } catch (InvalidArgumentException $e) {
            $this->logger->notice(\sprintf('OTLP %s parse rejected.', $signalLabel), [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
            ]);

            return $this->otlpIngestGateway->respond('invalid otlp payload', Response::HTTP_BAD_REQUEST);
        }

        if ([] === $payloads) {
            // Valid OTLP with only dropped items — ACK without queue work.
            return $this->otlpIngestGateway->respond('', Response::HTTP_OK);
        }

        $envelope = $mapper->toEnvelopeBody($payloads);
        $this->bus->dispatch(new ProcessEnvelopeMessage(
            $projectId,
            $envelope,
            new DateTimeImmutable()->format(DateTimeInterface::ATOM),
        ));

        return $this->otlpIngestGateway->respond('', Response::HTTP_OK);
    }
}
