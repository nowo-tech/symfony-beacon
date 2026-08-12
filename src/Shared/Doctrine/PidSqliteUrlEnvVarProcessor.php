<?php

declare(strict_types=1);

namespace App\Shared\Doctrine;

use Closure;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * Rewrites a SQLite file URL to a per-process path (PID suffix) without mutating
 * $_ENV / putenv — required for FrankenPHP worker-safe PHPUnit isolation.
 *
 * Example: sqlite:////dev/shm/foo.db → sqlite:////dev/shm/foo-12345.db
 */
final class PidSqliteUrlEnvVarProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, Closure $getEnv): mixed
    {
        $url = $getEnv($name);
        if (!\is_string($url)) {
            throw new RuntimeException(\sprintf('Env var "%s" resolved by pid_sqlite must be a string.', $name));
        }

        if (!str_starts_with($url, 'sqlite:///') || str_starts_with($url, 'sqlite:///:memory:')) {
            return $url;
        }

        $path = substr($url, \strlen('sqlite:///'));
        if (1 === preg_match('/-\d+\.db$/', $path)) {
            return $url;
        }

        if (!str_ends_with($path, '.db')) {
            return $url;
        }

        return 'sqlite:///'.substr($path, 0, -\strlen('.db')).'-'.getmypid().'.db';
    }

    public static function getProvidedTypes(): array
    {
        return ['pid_sqlite' => 'string'];
    }
}
