<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Service;

use App\Identity\Service\SocialLoginCredentialSeeder;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\AuthKitBundle\Entity\SocialLoginCredential;
use Nowo\AuthKitBundle\Repository\SocialLoginCredentialRepository;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class SocialLoginCredentialSeederTest extends TestCase
{
    private EntityManagerInterface&Stub $entityManager;
    private SocialLoginCredentialRepository&Stub $credentials;
    private int $flushCount = 0;
    private int $persistCount = 0;
    private int $removeCount = 0;
    private SocialLoginCredentialSeeder $seeder;

    protected function setUp(): void
    {
        $this->flushCount = 0;
        $this->persistCount = 0;
        $this->removeCount = 0;
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->entityManager->method('persist')->willReturnCallback(function (): void {
            ++$this->persistCount;
        });
        $this->entityManager->method('remove')->willReturnCallback(function (): void {
            ++$this->removeCount;
        });
        $this->entityManager->method('flush')->willReturnCallback(function (): void {
            ++$this->flushCount;
        });
        $this->credentials = $this->createStub(SocialLoginCredentialRepository::class);
        $this->seeder = new SocialLoginCredentialSeeder($this->entityManager, $this->credentials);
    }

    public function testUpsertCreatesThenUpdatesWithoutDuplicatePersist(): void
    {
        $this->credentials->method('findOneByProvider')->willReturn(null);
        $created = $this->seeder->upsert('google', '', 'id', 'secret', true, scopes: ['openid']);
        self::assertSame('Google', $created->getLabel());
        self::assertSame(1, $this->persistCount);
        self::assertSame(1, $this->flushCount);

        $this->credentials = $this->createStub(SocialLoginCredentialRepository::class);
        $this->credentials->method('findOneByProvider')->willReturn($created);
        $this->seeder = new SocialLoginCredentialSeeder($this->entityManager, $this->credentials);
        $updated = $this->seeder->upsert(
            'google',
            'Work Google',
            'id2',
            'secret2',
            false,
            authorizeUrl: 'https://auth',
            flush: false,
            enterpriseSso: true,
        );
        self::assertSame($created, $updated);
        self::assertSame('Work Google', $updated->getLabel());
        self::assertFalse($updated->isEnabled());
        self::assertTrue($updated->isEnterpriseSso());
        self::assertSame(1, $this->persistCount);
        self::assertSame(1, $this->flushCount);
    }

    public function testDeleteRemovesAndFlushes(): void
    {
        $credential = new SocialLoginCredential()->setProvider('github');
        $this->seeder->delete($credential);
        self::assertSame(1, $this->removeCount);
        self::assertSame(1, $this->flushCount);
        self::assertContains('google', SocialLoginCredentialSeeder::BUILTIN_PROVIDERS);
    }
}
