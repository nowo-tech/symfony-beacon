<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Command;

use App\Project\Command\ClearLegacyApiKeySecretsCommand;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Repository\ProjectApiKeyRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Console\Tester\CommandTester;

final class ClearLegacyApiKeySecretsCommandTest extends TestCase
{
    public function testDryRunReportsRedundantAndLegacyOnly(): void
    {
        $redundant = ProjectApiKey::generate(new Project()->setName('P')->setSlug('p'), 'Primary', 'pub-a', 'plain-secret-a');
        new ReflectionProperty(ProjectApiKey::class, 'secretKey')->setValue($redundant, 'leftover-cipher');

        $legacyOnly = new ProjectApiKey();
        $legacyOnly->setPublicKey('pub-b');
        new ReflectionProperty(ProjectApiKey::class, 'secretKey')->setValue($legacyOnly, 'legacy-plain');
        new ReflectionProperty(ProjectApiKey::class, 'secretHash')->setValue($legacyOnly, null);

        $repo = $this->createStub(ProjectApiKeyRepository::class);
        $repo->method('findAll')->willReturn([$redundant, $legacyOnly]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $tester = new CommandTester(new ClearLegacyApiKeySecretsCommand($repo, $em));
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('redundant secret_key+hash', $tester->getDisplay());
        self::assertStringContainsString('legacy-only', $tester->getDisplay());
    }

    public function testApplyClearsRedundantLegacyCiphertext(): void
    {
        $redundant = ProjectApiKey::generate(new Project()->setName('P')->setSlug('p'), 'Primary', 'pub-c', 'plain-secret-c');
        new ReflectionProperty(ProjectApiKey::class, 'secretKey')->setValue($redundant, 'leftover-cipher');
        self::assertTrue($redundant->hasLegacyEncryptedSecret());

        $repo = $this->createStub(ProjectApiKeyRepository::class);
        $repo->method('findAll')->willReturn([$redundant]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $tester = new CommandTester(new ClearLegacyApiKeySecretsCommand($repo, $em));
        self::assertSame(0, $tester->execute(['--apply' => true]));
        self::assertFalse($redundant->hasLegacyEncryptedSecret());
        self::assertStringContainsString('Cleared 1', $tester->getDisplay());
    }
}
