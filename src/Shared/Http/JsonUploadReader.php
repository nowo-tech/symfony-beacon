<?php

declare(strict_types=1);

namespace App\Shared\Http;

use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Decode a JSON object upload with an explicit size cap (DoS guard for admin/settings imports).
 */
final class JsonUploadReader
{
    /** Default 2 MiB — aligned with kit JSON import defaults and envelope body default. */
    public const int DEFAULT_MAX_BYTES = 2_097_152;

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException with message {@code missing_file}, {@code too_large}, or {@code invalid_json}
     */
    public static function decodeObject(?UploadedFile $file, int $maxBytes = self::DEFAULT_MAX_BYTES): array
    {
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw new InvalidArgumentException('missing_file');
        }

        $limit = max(1, $maxBytes);
        $size = $file->getSize();
        if (false === $size || $size > $limit) {
            throw new InvalidArgumentException('too_large');
        }

        $path = $file->getPathname();
        if (!is_readable($path)) {
            throw new InvalidArgumentException('missing_file');
        }

        $raw = file_get_contents($path);
        if (false === $raw) {
            throw new InvalidArgumentException('missing_file');
        }

        if (\strlen($raw) > $limit) {
            throw new InvalidArgumentException('too_large');
        }

        try {
            $payload = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('invalid_json');
        }

        if (!\is_array($payload) || array_is_list($payload)) {
            throw new InvalidArgumentException('invalid_json');
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }
}
