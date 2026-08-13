<?php

declare(strict_types=1);

namespace App\Tests\Unit\Performance\Service;

use App\Analytics\Entity\DailyProjectStat;
use App\Analytics\Repository\DailyProjectStatRepository;
use App\Performance\Entity\PerfTransaction;
use App\Performance\Repository\PerfTransactionRepository;
use App\Performance\Service\NPlusOneDetector;
use App\Performance\Service\PerformanceDemoSeeder;
use App\Project\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class PerformanceDemoSeederTest extends TestCase
{
    public function testSeedIfEmptyReturnsFalseWhenDemoExists(): void
    {
        $tx = $this->createStub(PerfTransactionRepository::class);
        $tx->method('findOneBy')->willReturn(new PerfTransaction());
        $seeder = new PerformanceDemoSeeder(
            $this->createStub(EntityManagerInterface::class),
            $tx,
            $this->createStub(DailyProjectStatRepository::class),
            new NPlusOneDetector(),
        );
        self::assertFalse($seeder->seedIfEmpty(new Project()));
    }

    public function testSeedIfEmptyPersistsNPlusOneAndCleanTransactions(): void
    {
        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $em->method('flush');

        $tx = $this->createStub(PerfTransactionRepository::class);
        $tx->method('findOneBy')->willReturn(null);
        $stat = new DailyProjectStat();
        $stats = $this->createStub(DailyProjectStatRepository::class);
        $stats->method('findOrCreate')->willReturn($stat);

        $seeder = new PerformanceDemoSeeder($em, $tx, $stats, new NPlusOneDetector());
        self::assertTrue($seeder->seedIfEmpty(new Project()));

        $names = array_map(
            static fn (object $e): ?string => $e instanceof PerfTransaction ? $e->getTransactionName() : null,
            $persisted,
        );
        self::assertContains(PerformanceDemoSeeder::NPLUS1_TRANSACTION, $names);
        self::assertContains(PerformanceDemoSeeder::CLEAN_TRANSACTION, $names);
        self::assertSame(2, $stat->getTransactionCount());
        self::assertGreaterThan(0, $stat->getNPlusOneCount());
    }
}
