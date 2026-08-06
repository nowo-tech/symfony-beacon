<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\Command\SeedDemoCommand;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use PHPUnit\Framework\TestCase;

final class DemoApiKeyStableSecretTest extends TestCase
{
    public function testGenerateAcceptsStableDemoSecret(): void
    {
        $project = new Project();
        $project->setName(SeedDemoCommand::DEMO_PROJECT_NAME);
        $project->setSlug(SeedDemoCommand::DEMO_PROJECT_SLUG);

        $key = ProjectApiKey::generate(
            $project,
            SeedDemoCommand::DEMO_API_KEY_NAME,
            SeedDemoCommand::DEMO_PUBLIC_KEY,
            SeedDemoCommand::DEMO_SECRET_KEY,
        );

        self::assertSame(SeedDemoCommand::DEMO_PUBLIC_KEY, $key->getPublicKey());
        self::assertSame(SeedDemoCommand::DEMO_SECRET_KEY, $key->getSecretKey());

        $dsn = $key->buildDsn(SeedDemoCommand::SELF_INGEST_BASE_URL);
        self::assertStringStartsWith('http://'.SeedDemoCommand::DEMO_PUBLIC_KEY.':'.SeedDemoCommand::DEMO_SECRET_KEY.'@127.0.0.1/', $dsn);
    }
}
