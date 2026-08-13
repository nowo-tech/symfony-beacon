<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Service;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\PortableUserProvisioner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PortableUserProvisionerTest extends TestCase
{
    public function testCreatesDisabledUserWithHashedPassword(): void
    {
        $saved = null;
        $flushFlag = null;
        $repo = $this->createStub(UserRepository::class);
        $repo->method('save')->willReturnCallback(static function (User $user, bool $flush) use (&$saved, &$flushFlag): void {
            $saved = $user;
            $flushFlag = $flush;
        });
        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed');

        $user = new PortableUserProvisioner($repo, $hasher)->createDisabledUser('a@example.com', '', true);

        self::assertSame($user, $saved);
        self::assertTrue($flushFlag);
        self::assertSame('a@example.com', $user->getEmail());
        self::assertSame('a@example.com', $user->getDisplayName());
        self::assertFalse($user->isEnabled());
        self::assertSame('hashed', $user->getPassword());
        self::assertNotNull($user->getPasswordChangedAt());
    }

    public function testUsesDisplayNameWhenProvidedAndDefaultsFlushFalse(): void
    {
        $flushFlag = true;
        $repo = $this->createStub(UserRepository::class);
        $repo->method('save')->willReturnCallback(static function (User $user, bool $flush) use (&$flushFlag): void {
            $flushFlag = $flush;
        });
        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed');

        $user = new PortableUserProvisioner($repo, $hasher)->createDisabledUser('b@example.com', 'Bob');

        self::assertSame('Bob', $user->getDisplayName());
        self::assertFalse($flushFlag);
    }
}
