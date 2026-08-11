<?php

declare(strict_types=1);

namespace App\Ingest\Otlp\Service;

use App\Project\Entity\Project;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * OTLP HTTP ingress gate used by {@see OtlpIngestPipeline}.
 */
interface OtlpIngestGatewayInterface
{
    /**
     * @return Response|array{project: Project, body: string}
     */
    public function accept(string $projectRef, Request $request): Response|array;

    /**
     * @param array<string, string> $extraHeaders
     */
    public function respond(string $content, int $status, array $extraHeaders = []): Response;
}
