<?php

declare(strict_types=1);

namespace App\Issues\Service;

/**
 * Prepares stack frames for issue/event UI (innermost first; open preferred in-app).
 */
final class IssueStackPresenter
{
    /**
     * Payload frames are typically outermost-first; display is innermost-first.
     *
     * @param mixed $frames Envelope stack frames (list or invalid)
     *
     * @return list<array{frame: array<string, mixed>, open: bool}>
     */
    public function displayFrames(mixed $frames): array
    {
        if (!\is_array($frames) || [] === $frames) {
            return [];
        }

        $normalized = [];
        foreach ($frames as $frame) {
            if (\is_array($frame)) {
                $normalized[] = $frame;
            }
        }
        if ([] === $normalized) {
            return [];
        }

        $display = array_reverse($normalized);
        $openAt = 0;
        foreach ($display as $i => $frame) {
            if (!empty($frame['in_app'])) {
                $openAt = $i;
                break;
            }
        }

        $out = [];
        foreach ($display as $i => $frame) {
            $out[] = [
                'frame' => $frame,
                'open' => $i === $openAt,
            ];
        }

        return $out;
    }
}
