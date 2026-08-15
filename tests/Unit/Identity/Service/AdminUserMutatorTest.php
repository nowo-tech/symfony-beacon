<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Service;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\AdminUserMutator;
use App\Identity\Service\UserActionRecorder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminUserMutatorTest extends TestCase
{
    public function testCreateRejectsDuplicateEmail(): void
    {
        $users = $this->createStub(UserRepository::class);
        $users->method('findOneByEmail')->willReturn(new User());
        $em = $this->createStub(EntityManagerInterface::class);
        $mutator = new AdminUserMutator(
            $users,
            new UserActionRecorder($em, new RequestStack()),
            $em,
            $this->createStub(UserPasswordHasherInterface::class),
        );

        self::assertSame(
            'email_taken',
            $mutator->create(new User(), 'a@example.com', 'A', 'secret', 'user', true),
        );
    }

    public function testCannotChangeOwnRoleOrDisableSelf(): void
    {
        $actor = new User();
        new ReflectionProperty(User::class, 'id')->setValue($actor, 7);

        $em = $this->createStub(EntityManagerInterface::class);
        $mutator = new AdminUserMutator(
            $this->createStub(UserRepository::class),
            new UserActionRecorder($em, new RequestStack()),
            $em,
            $this->createStub(UserPasswordHasherInterface::class),
        );

        self::assertSame('cannot_change_own', $mutator->changeInstanceRole($actor, $actor, 'admin'));
        self::assertSame('cannot_disable_self', $mutator->toggleEnabled($actor, $actor));
    }
}
