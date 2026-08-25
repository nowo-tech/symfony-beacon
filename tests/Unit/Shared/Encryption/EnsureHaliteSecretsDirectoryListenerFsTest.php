<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Encryption;

use App\Shared\Encryption\EnsureHaliteSecretsDirectoryListener;
use App\Shared\Encryption\HaliteSecretsFilesystem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EnsureHaliteSecretsDirectoryListenerFsTest extends TestCase
{
    public function testConsoleCommandThrowsWhenDirectoryCannotBeCreated(): void
    {
        /** @var HaliteSecretsFilesystem&MockObject $filesystem */
        $filesystem = $this->createMock(HaliteSecretsFilesystem::class);
        $filesystem->method('isDirectory')->willReturn(false);
        $filesystem->method('makeDirectory')->willReturn(false);

        $listener = new EnsureHaliteSecretsDirectoryListener('/virtual/project', $filesystem);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to create Halite secrets directory');
        $listener->onConsoleCommand();
    }

    public function testEnsureSkipsNonFilesUnknownPermsAndFailedChmod(): void
    {
        /** @var HaliteSecretsFilesystem&MockObject $filesystem */
        $filesystem = $this->createMock(HaliteSecretsFilesystem::class);
        $filesystem->method('isDirectory')->willReturn(true);
        $filesystem->method('glob')->willReturn(['skip.key', 'perms.key', 'chmod.key']);
        $filesystem->method('isFile')->willReturnCallback(
            static fn (string $file): bool => 'skip.key' !== $file,
        );
        $filesystem->method('filePerms')->willReturnCallback(
            static fn (string $file): int|false => match ($file) {
                'perms.key' => false,
                'chmod.key' => 0644,
                default => 0600,
            },
        );
        $filesystem->method('chmod')->willReturn(false);

        $listener = new EnsureHaliteSecretsDirectoryListener('/virtual/project', $filesystem);
        $listener->onConsoleCommand();

        $this->addToAssertionCount(1);
    }
}
