<?php

declare(strict_types=1);

namespace App\Ingest\Service;

use InvalidArgumentException;

/**
 * Parses Envelope wire format (newline-delimited JSON header + items).
 */
final class EnvelopeParser
{
    /**
     * @return array{
     *     header: array<string, mixed>,
     *     items: list<array{header: array<string, mixed>, payload: array<string, mixed>|string|null}>
     * }
     */
    public function parse(string $body): array
    {
        $body = str_replace("\r\n", "\n", $body);
        $lines = explode("\n", $body);
        if ('' === trim($lines[0] ?? '')) {
            throw new InvalidArgumentException('Empty envelope');
        }

        $header = json_decode($lines[0], true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($header)) {
            throw new InvalidArgumentException('Invalid envelope header');
        }

        $items = [];
        $i = 1;
        $count = \count($lines);
        while ($i < $count) {
            if ('' === trim($lines[$i])) {
                ++$i;
                continue;
            }

            $itemHeader = json_decode($lines[$i], true, 512, \JSON_THROW_ON_ERROR);
            if (!\is_array($itemHeader)) {
                throw new InvalidArgumentException('Invalid item header');
            }
            ++$i;

            $payload = null;
            if ($i < $count && '' !== trim($lines[$i] ?? '')) {
                $raw = $lines[$i];
                ++$i;
                $decoded = json_decode($raw, true);
                $payload = \is_array($decoded) ? $decoded : $raw;
            }

            $items[] = ['header' => $itemHeader, 'payload' => $payload];
        }

        return ['header' => $header, 'items' => $items];
    }
}
