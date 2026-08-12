<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingest;

use App\Ingest\Message\ProcessEnvelopeMessage;
use App\Ingest\Otlp\Service\OtlpIngestGatewayInterface;
use App\Ingest\Otlp\Service\OtlpIngestPipeline;
use App\Ingest\Otlp\Service\OtlpSignalMapperInterface;
use App\Project\Entity\Project;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class OtlpIngestPipelineTest extends TestCase
{
    public function testReturnsGatewayResponseWithoutMappingOrDispatch(): void
    {
        $request = Request::create('/otlp', Request::METHOD_POST, content: '{}');
        $denied = new Response('forbidden', Response::HTTP_FORBIDDEN);

        $gateway = $this->createMock(OtlpIngestGatewayInterface::class);
        $gateway->expects(self::once())
            ->method('accept')
            ->with('7', $request)
            ->willReturn($denied);
        $gateway->expects(self::never())->method('respond');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $pipeline = new OtlpIngestPipeline(
            $gateway,
            $bus,
            $this->createStub(LoggerInterface::class),
        );

        $response = $pipeline->ingest(
            '7',
            $request,
            $this->createStub(OtlpSignalMapperInterface::class),
            'logs',
        );

        self::assertSame($denied, $response);
    }

    public function testInvalidMapperPayloadReturnsBadRequest(): void
    {
        $request = Request::create('/otlp', Request::METHOD_POST, content: '{bad');
        $project = $this->projectWithId(3);

        $gateway = $this->createMock(OtlpIngestGatewayInterface::class);
        $gateway->expects(self::once())->method('accept')->willReturn([
            'project' => $project,
            'body' => '{bad',
        ]);
        $gateway->expects(self::once())
            ->method('respond')
            ->with('invalid otlp payload', Response::HTTP_BAD_REQUEST)
            ->willReturn(new Response('invalid otlp payload', Response::HTTP_BAD_REQUEST));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('notice');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $mapper = $this->createMock(OtlpSignalMapperInterface::class);
        $mapper->expects(self::once())
            ->method('mapToEventPayloads')
            ->willThrowException(new InvalidArgumentException('broken json'));

        $pipeline = new OtlpIngestPipeline($gateway, $bus, $logger);
        $response = $pipeline->ingest('3', $request, $mapper, 'traces');

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testEmptyPayloadsAckWithoutDispatch(): void
    {
        $request = Request::create('/otlp', Request::METHOD_POST, content: '{}');
        $project = $this->projectWithId(1);

        $gateway = $this->createMock(OtlpIngestGatewayInterface::class);
        $gateway->expects(self::once())->method('accept')->willReturn([
            'project' => $project,
            'body' => '{}',
        ]);
        $gateway->expects(self::once())
            ->method('respond')
            ->with('', Response::HTTP_OK)
            ->willReturn(new Response('', Response::HTTP_OK));

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $mapper = $this->createMock(OtlpSignalMapperInterface::class);
        $mapper->expects(self::once())->method('mapToEventPayloads')->willReturn([]);
        $mapper->expects(self::never())->method('toEnvelopeBody');

        $pipeline = new OtlpIngestPipeline(
            $gateway,
            $bus,
            $this->createStub(LoggerInterface::class),
        );
        $response = $pipeline->ingest('1', $request, $mapper, 'metrics');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testNonEmptyPayloadsDispatchProcessEnvelopeMessage(): void
    {
        $request = Request::create('/otlp', Request::METHOD_POST, content: '{"ok":true}');
        $project = $this->projectWithId(42);
        $payloads = [['message' => 'boom']];
        $envelopeBody = "header\n{\"message\":\"boom\"}\n";

        $gateway = $this->createMock(OtlpIngestGatewayInterface::class);
        $gateway->expects(self::once())->method('accept')->willReturn([
            'project' => $project,
            'body' => '{"ok":true}',
        ]);
        $gateway->expects(self::once())
            ->method('respond')
            ->with('', Response::HTTP_OK)
            ->willReturn(new Response('', Response::HTTP_OK));

        $mapper = $this->createMock(OtlpSignalMapperInterface::class);
        $mapper->expects(self::once())->method('mapToEventPayloads')->willReturn($payloads);
        $mapper->expects(self::once())->method('toEnvelopeBody')->with($payloads)->willReturn($envelopeBody);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (object $message): bool => $message instanceof ProcessEnvelopeMessage
                && 42 === $message->projectId
                && $envelopeBody === $message->rawEnvelope
                && '' !== $message->receivedAtIso))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $pipeline = new OtlpIngestPipeline(
            $gateway,
            $bus,
            $this->createStub(LoggerInterface::class),
        );
        $response = $pipeline->ingest('019fea2d-507b-7890-8b33-ca488db6f696', $request, $mapper, 'logs');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    private function projectWithId(int $id): Project
    {
        $project = new Project();
        $prop = new ReflectionProperty(Project::class, 'id');
        $prop->setValue($project, $id);

        return $project;
    }
}
