<?php

declare(strict_types=1);

namespace App\Ingest\Otlp\Service;

/**
 * Maps one OTLP signal body to Beacon event payloads and an Envelope wire body.
 */
interface OtlpSignalMapperInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function mapToEventPayloads(string $jsonBody): array;

    /**
     * @param list<array<string, mixed>> $payloads
     */
    public function toEnvelopeBody(array $payloads): string;
}
