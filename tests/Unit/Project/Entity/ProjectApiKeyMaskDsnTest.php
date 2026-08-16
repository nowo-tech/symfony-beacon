<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Entity;

use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class ProjectApiKeyMaskDsnTest extends TestCase
{
    public function testMaskDsnHidesSecretSegment(): void
    {
        $dsn = 'https://pk:supersecret@localhost:9447/019fea2d-507b-7890-8b33-ca488db6f696';
        self::assertSame(
            'https://pk:••••••••@localhost:9447/019fea2d-507b-7890-8b33-ca488db6f696',
            ProjectApiKey::maskDsn($dsn),
        );
    }

    public function testMaskDsnLeavesPublicOnlyDsnUnchanged(): void
    {
        $dsn = 'https://pkonly@localhost/uuid';
        self::assertSame($dsn, ProjectApiKey::maskDsn($dsn));
    }

    public function testSecretLifecycleAndLegacyUpgradePaths(): void
    {
        $project = new Project();
        $project->setName('Beacon');
        $project->setSlug('beacon');
        $project->ensureUuid();

        $key = ProjectApiKey::generate($project, 'Primary', 'pk_demo', 'sk_demo');

        self::assertSame($project, $key->getProject());
        self::assertSame('Primary', $key->getLabel());
        self::assertSame('pk_demo', $key->getPublicKey());
        self::assertTrue($key->matchesSecret('sk_demo'));
        self::assertFalse($key->matchesSecret('wrong'));
        self::assertSame('sk_demo', $key->peekIssuedPlainSecret());
        self::assertSame('sk_demo', $key->consumeIssuedPlainSecret());
        self::assertNull($key->consumeIssuedPlainSecret());
        self::assertSame(
            'https://pk_demo@localhost/'.$project->getUuid(),
            $key->buildDsn('https://localhost'),
        );

        $legacy = new ProjectApiKey();
        $legacy->setProject($project);
        $legacy->setPublicKey('pk_legacy');
        $legacy->setSecretKey('legacy-secret');

        self::assertTrue($legacy->matchesSecret('legacy-secret'));
        self::assertFalse($legacy->upgradeLegacySecretToHash('wrong'));
        self::assertTrue($legacy->upgradeLegacySecretToHash('legacy-secret'));
        self::assertFalse($legacy->upgradeLegacySecretToHash('legacy-secret'));
        self::assertNull($legacy->getSecretKey());
        self::assertTrue($legacy->matchesSecret('legacy-secret'));
        self::assertSame(
            'http://pk_legacy:legacy-secret@localhost:9447/'.$project->getUuid(),
            $legacy->buildDsn('http://localhost:9447', 'legacy-secret'),
        );
    }

    public function testSimpleAccessorsAndFallbackMasking(): void
    {
        $project = new Project();
        $project->setName('Beacon');
        $project->setSlug('beacon');

        $key = new ProjectApiKey();
        new ReflectionProperty(ProjectApiKey::class, 'id')->setValue($key, 42);

        $createdAt = $key->getCreatedAt();
        $key
            ->setProject($project)
            ->setPublicKey('pk_test')
            ->setLabel('Ops')
            ->setActive(false);

        self::assertSame(42, $key->getId());
        self::assertSame($project, $key->getProject());
        self::assertSame('pk_test', $key->getPublicKey());
        self::assertSame('Ops', $key->getLabel());
        self::assertFalse($key->isActive());
        self::assertSame($createdAt, $key->getCreatedAt());
        self::assertSame('not-a-dsn', ProjectApiKey::maskDsn('not-a-dsn'));
        self::assertFalse($key->upgradeLegacySecretToHash('missing'));
    }
}
