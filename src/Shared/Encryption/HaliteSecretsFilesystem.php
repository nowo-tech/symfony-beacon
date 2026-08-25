<?php

declare(strict_types=1);

namespace App\Shared\Encryption;

/**
 * Filesystem seam for Halite key-directory hardening (mockable in unit tests).
 */
class HaliteSecretsFilesystem
{
    public function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    public function makeDirectory(string $directory, int $permissions): bool
    {
        return mkdir($directory, $permissions, true);
    }

    /**
     * @return list<string>|false
     */
    public function glob(string $pattern): array|false
    {
        return glob($pattern);
    }

    public function isFile(string $filename): bool
    {
        return is_file($filename);
    }

    public function filePerms(string $filename): int|false
    {
        return fileperms($filename);
    }

    public function chmod(string $filename, int $permissions): bool
    {
        return chmod($filename, $permissions);
    }
}
