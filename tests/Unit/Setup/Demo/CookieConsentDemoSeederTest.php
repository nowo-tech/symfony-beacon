<?php

declare(strict_types=1);

namespace App\Tests\Unit\Setup\Demo;

use App\Setup\Demo\CookieConsentDemoSeeder;
use App\Setup\Demo\DemoFixtureLoader;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use PHPUnit\Framework\TestCase;

final class CookieConsentDemoSeederTest extends TestCase
{
    public function testSeedIfEmptyCreatesDefaultProfileAndCookies(): void
    {
        $persisted = [];
        $flush = 0;
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $em->method('flush')->willReturnCallback(static function () use (&$flush): void {
            ++$flush;
        });

        $configs = $this->createStub(CookieConsentConfigRepository::class);
        $configs->method('findDefaultEnabled')->willReturn(null);
        $definitions = $this->createStub(CookieDefinitionRepository::class);
        $definitions->method('findByConfigOrdered')->willReturn([]);

        $seeder = new CookieConsentDemoSeeder($em, $configs, $definitions, new DemoFixtureLoader());
        self::assertTrue($seeder->seedIfEmpty());
        self::assertGreaterThanOrEqual(2, $flush);
        self::assertNotEmpty(array_filter($persisted, static fn (object $e): bool => $e instanceof CookieConsentConfig));
        self::assertNotEmpty(array_filter($persisted, static fn (object $e): bool => $e instanceof CookieDefinition));
    }

    public function testSeedIfEmptyUpdatesExistingEmptyInventory(): void
    {
        $config = new CookieConsentConfig()->setName('old')->setDefault(true)->setEnabled(true);
        $configs = $this->createStub(CookieConsentConfigRepository::class);
        $configs->method('findDefaultEnabled')->willReturn($config);
        $definitions = $this->createStub(CookieDefinitionRepository::class);
        $definitions->method('findByConfigOrdered')->willReturn([]);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist');
        $em->method('flush');

        self::assertTrue(new CookieConsentDemoSeeder($em, $configs, $definitions, new DemoFixtureLoader())->seedIfEmpty());
        self::assertSame('bottom', $config->getConsentModalPositionY());
        self::assertSame('left', $config->getConsentModalPositionX());
        self::assertTrue($config->isConsentModalEqualWeightButtons());
        self::assertSame('bottom', $config->getPreferencesModalPositionY());
        self::assertSame('left', $config->getPreferencesModalPositionX());
    }
}
