<?php

declare(strict_types=1);

namespace App\Setup\Demo;

use InvalidArgumentException;
use JsonException;

/**
 * Loads demo fixture JSON files stored next to the demo seeders.
 */
final readonly class DemoFixtureLoader
{
    /**
     * @return array<mixed>
     */
    public function load(string $fixtureName): array
    {
        $path = __DIR__.'/fixtures/'.$fixtureName;
        if (!is_file($path)) {
            throw new InvalidArgumentException(\sprintf('Demo fixture "%s" was not found at "%s".', $fixtureName, $path));
        }

        $json = file_get_contents($path);
        if (false === $json) {
            throw new InvalidArgumentException(\sprintf('Demo fixture "%s" could not be read at "%s".', $fixtureName, $path));
        }

        try {
            $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(\sprintf('Demo fixture "%s" contains invalid JSON: %s', $fixtureName, $exception->getMessage()), previous: $exception);
        }

        if (!\is_array($decoded)) {
            throw new InvalidArgumentException(\sprintf('Demo fixture "%s" must decode to an array.', $fixtureName));
        }

        return $decoded;
    }
}
