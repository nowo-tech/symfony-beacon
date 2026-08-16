<?php

declare(strict_types=1);

namespace App\Shared\Encryption {
    final class EnsureHaliteFsHooks
    {
        public static $isDir = null;
        public static $mkdir = null;
        public static $glob = null;
        public static $isFile = null;
        public static $filePerms = null;
        public static $chmod = null;

        public static function reset(): void
        {
            self::$isDir = null;
            self::$mkdir = null;
            self::$glob = null;
            self::$isFile = null;
            self::$filePerms = null;
            self::$chmod = null;
        }
    }

    function is_dir(string $path): bool
    {
        return \is_callable(EnsureHaliteFsHooks::$isDir) ? (EnsureHaliteFsHooks::$isDir)($path) : \is_dir($path);
    }

    function mkdir(string $directory, int $permissions = 0777, bool $recursive = false): bool
    {
        return \is_callable(EnsureHaliteFsHooks::$mkdir) ? (EnsureHaliteFsHooks::$mkdir)($directory, $permissions, $recursive) : \mkdir($directory, $permissions, $recursive);
    }

    function glob(string $pattern, int $flags = 0): array|false
    {
        return \is_callable(EnsureHaliteFsHooks::$glob) ? (EnsureHaliteFsHooks::$glob)($pattern, $flags) : \glob($pattern, $flags);
    }

    function is_file(string $filename): bool
    {
        return \is_callable(EnsureHaliteFsHooks::$isFile) ? (EnsureHaliteFsHooks::$isFile)($filename) : \is_file($filename);
    }

    function fileperms(string $filename): int|false
    {
        return \is_callable(EnsureHaliteFsHooks::$filePerms) ? (EnsureHaliteFsHooks::$filePerms)($filename) : \fileperms($filename);
    }

    function chmod(string $filename, int $permissions): bool
    {
        return \is_callable(EnsureHaliteFsHooks::$chmod) ? (EnsureHaliteFsHooks::$chmod)($filename, $permissions) : \chmod($filename, $permissions);
    }
}

namespace App\Tests\Unit\Shared\Encryption {
    use App\Shared\Encryption\EnsureHaliteFsHooks;
    use App\Shared\Encryption\EnsureHaliteSecretsDirectoryListener;
    use PHPUnit\Framework\TestCase;

    final class EnsureHaliteSecretsDirectoryListenerFsTest extends TestCase
    {
        protected function tearDown(): void
        {
            EnsureHaliteFsHooks::reset();
        }

        public function testConsoleCommandThrowsWhenDirectoryCannotBeCreated(): void
        {
            EnsureHaliteFsHooks::$isDir = static fn (string $path): bool => false;
            EnsureHaliteFsHooks::$mkdir = static fn (string $directory, int $permissions, bool $recursive): bool => false;

            $listener = new EnsureHaliteSecretsDirectoryListener('/virtual/project');

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Unable to create Halite secrets directory');
            $listener->onConsoleCommand();
        }

        public function testEnsureSkipsNonFilesUnknownPermsAndFailedChmod(): void
        {
            EnsureHaliteFsHooks::$isDir = static fn (string $path): bool => true;
            EnsureHaliteFsHooks::$glob = static fn (string $pattern): array => ['skip.key', 'perms.key', 'chmod.key'];
            EnsureHaliteFsHooks::$isFile = static fn (string $file): bool => 'skip.key' !== $file;
            EnsureHaliteFsHooks::$filePerms = static function (string $file): int|false {
                return match ($file) {
                    'perms.key' => false,
                    'chmod.key' => 0644,
                    default => 0600,
                };
            };
            EnsureHaliteFsHooks::$chmod = static fn (string $file, int $permissions): bool => false;

            $listener = new EnsureHaliteSecretsDirectoryListener('/virtual/project');
            $listener->onConsoleCommand();

            self::assertTrue(true);
        }
    }
}
