<?php

declare(strict_types=1);

namespace App\Ingest\Otlp\Service;

use App\Ingest\Service\EnvelopeAuthParser;
use App\Ingest\Service\IngestProjectAccessGate;
use App\Ops\Metrics\MetricsCollector;
use App\Project\Entity\Project;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared OTLP HTTP ingress gate: body limits, auth parse, project access, metrics.
 *
 * Controllers keep OpenAPI attributes and signal-specific mapping/dispatch only.
 */
final readonly class OtlpIngestGateway implements OtlpIngestGatewayInterface
{
    public function __construct(
        private EnvelopeAuthParser $authParser,
        private IngestProjectAccessGate $projectAccessGate,
        private MetricsCollector $metricsCollector,
        private InstanceOpsDefaults $opsDefaults,
    ) {
    }

    /**
     * Validate request and authorize ingest for `{projectId}` (UUID or legacy numeric id).
     *
     * @return Response|array{project: Project, body: string} Error response, or accepted body + project
     */
    public function accept(string $projectRef, Request $request): Response|array
    {
        $body = $request->getContent();
        if ('' === $body) {
            return $this->respond('Empty body', Response::HTTP_BAD_REQUEST);
        }

        if (\strlen($body) > $this->opsDefaults->envelopeMaxBytes()) {
            return $this->respond('otlp body too large', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        if ($this->authParser->queryContainsCredentials($request->server->get('QUERY_STRING', ''))) {
            return $this->respond(
                'query string authorization is not supported for OTLP; use X-Beacon-Auth',
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $auth = $this->authParser->parseFromRequest(
            $request->headers->get('X-Beacon-Auth'),
        );

        $access = $this->projectAccessGate->authorize($projectRef, $auth['public_key'], $auth['secret_key']);
        if (!$access['ok']) {
            return $this->respond($access['message'], $access['status'], $access['headers']);
        }

        return [
            'project' => $access['project'],
            'body' => $body,
        ];
    }

    /**
     * @param array<string, string> $extraHeaders
     */
    public function respond(string $content, int $status, array $extraHeaders = []): Response
    {
        if (Response::HTTP_OK === $status) {
            $this->metricsCollector->recordIngestAck();
        } else {
            $reason = match (true) {
                Response::HTTP_UNAUTHORIZED === $status => 'unauthorized',
                Response::HTTP_FORBIDDEN === $status => 'forbidden',
                Response::HTTP_TOO_MANY_REQUESTS === $status && str_contains($content, 'rate limit') => 'rate_limit',
                Response::HTTP_TOO_MANY_REQUESTS === $status => 'quota',
                Response::HTTP_BAD_REQUEST === $status => 'invalid',
                Response::HTTP_REQUEST_ENTITY_TOO_LARGE === $status => 'too_large',
                default => 'other',
            };
            $this->metricsCollector->recordIngestReject($reason);
        }

        return new Response($content, $status, $extraHeaders);
    }
}
