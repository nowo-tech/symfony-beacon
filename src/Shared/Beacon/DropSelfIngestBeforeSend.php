<?php

declare(strict_types=1);

namespace App\Shared\Beacon;

/**
 * Drops Beacon client payloads that originate from Envelope ingest HTTP requests.
 *
 * Prevents a feedback loop when this server dogfoods itself via BEACON_DSN:
 * an ingest failure must not be re-reported through the same ingest path.
 *
 * @phpstan-type BeaconEvent array<string, mixed>
 */
final class DropSelfIngestBeforeSend
{
    /**
     * @param BeaconEvent $event
     *
     * @return BeaconEvent|null Mutated payload, or null to drop the send
     */
    public function __invoke(array $event): ?array
    {
        if ($this->isEnvelopeIngestRequest($event)) {
            return null;
        }

        return $event;
    }

    /**
     * @param BeaconEvent $event
     */
    private function isEnvelopeIngestRequest(array $event): bool
    {
        $candidates = [];

        $request = $event['request'] ?? null;
        if (\is_array($request)) {
            foreach (['url', 'path', 'uri'] as $key) {
                if (isset($request[$key]) && \is_string($request[$key])) {
                    $candidates[] = $request[$key];
                }
            }
        }

        $contexts = $event['contexts'] ?? null;
        if (\is_array($contexts)) {
            $ctxRequest = $contexts['request'] ?? null;
            if (\is_array($ctxRequest)) {
                foreach (['url', 'path', 'uri'] as $key) {
                    if (isset($ctxRequest[$key]) && \is_string($ctxRequest[$key])) {
                        $candidates[] = $ctxRequest[$key];
                    }
                }
            }
        }

        $transaction = $event['transaction'] ?? null;
        if (\is_string($transaction)) {
            $candidates[] = $transaction;
        }

        return array_any($candidates, fn (string $value): bool => $this->pathLooksLikeEnvelopeIngest($value));
    }

    private function pathLooksLikeEnvelopeIngest(string $value): bool
    {
        $path = parse_url($value, \PHP_URL_PATH);
        if (!\is_string($path) || '' === $path) {
            $path = $value;
        }

        return str_contains($path, '/envelope');
    }
}
