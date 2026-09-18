<?php

declare(strict_types=1);

namespace App\Notifications\Service;

/**
 * Whether {@see \PHP_BINARY} can run `php -r`.
 *
 * FrankenPHP HTTP requests report an empty binary or the `frankenphp` binary.
 * That binary rejects `-r`, so the outbound-url-guard DNS child never starts.
 */
final readonly class PhpCliProbe
{
    public function __construct(
        private ?string $binary = null,
        private ?string $sapi = null,
    ) {
    }

    public function supportsDashR(): bool
    {
        $binary = $this->binary ?? \PHP_BINARY;
        $sapi = $this->sapi ?? \PHP_SAPI;
        if ('frankenphp' === $sapi || '' === $binary || !is_file($binary)) {
            return false;
        }

        return !str_contains(strtolower(basename($binary)), 'frankenphp');
    }
}
