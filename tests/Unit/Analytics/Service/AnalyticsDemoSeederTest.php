<?php

declare(strict_types=1);

namespace App\Tests\Unit\Analytics\Service;

use App\Analytics\Entity\DailyProjectStat;
use App\Analytics\Repository\DailyProjectStatRepository;
use App\Analytics\Service\AnalyticsDemoSeeder;
use App\Project\Entity\Project;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class AnalyticsDemoSeederTest extends TestCase
{
    public function testSeedWindowCreatesMissingDaysOnly(): void
    {
        $createdDates = [];
        $flush = 0;
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willReturnCallback(static function () use (&$flush): void {
            ++$flush;
        });

        $existingDay = new DateTimeImmutable('today')->setTime(0, 0);
        $stats = $this->createStub(DailyProjectStatRepository::class);
        $stats->method('findOneBy')->willReturnCallback(
            static function (array $criteria) use ($existingDay): ?DailyProjectStat {
                $day = $criteria['statDate'] ?? null;

                return $day instanceof DateTimeImmutable && $day == $existingDay
                    ? new DailyProjectStat()
                    : null;
            },
        );
        $stats->method('findOrCreate')->willReturnCallback(
            static function (Project $project, DateTimeImmutable $day) use (&$createdDates): DailyProjectStat {
                $createdDates[] = $day->format('Y-m-d');

                return new DailyProjectStat();
            },
        );

        $seeder = new AnalyticsDemoSeeder($em, $stats);
        self::assertTrue($seeder->seedWindow(new Project(), 3));
        self::assertCount(2, $createdDates); // today already exists → 2 missing
        self::assertGreaterThanOrEqual(1, $flush);

        // All days present → false
        $statsFull = $this->createStub(DailyProjectStatRepository::class);
        $statsFull->method('findOneBy')->willReturn(new DailyProjectStat());
        self::assertFalse(new AnalyticsDemoSeeder($em, $statsFull)->seedIfEmpty(new Project()));
    }
}
