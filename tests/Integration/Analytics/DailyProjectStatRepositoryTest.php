<?php

declare(strict_types=1);

namespace App\Tests\Integration\Analytics;

use App\Analytics\Entity\DailyProjectStat;
use App\Analytics\Repository\DailyProjectStatRepository;
use App\Project\Entity\Project;
use App\Tests\Support\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class DailyProjectStatRepositoryTest extends DatabaseWebTestCase
{
    public function testFindOrCreateAndRangeQueries(): void
    {
        [, , $project] = $this->bootWithDemoProject('daily-stats-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $repository = self::getContainer()->get(DailyProjectStatRepository::class);

        $otherProject = new Project()
            ->setName('Other')
            ->setSlug('other-stats');
        $em->persist($otherProject);
        $em->flush();

        $today = new DateTimeImmutable('today');
        $minusOne = $today->modify('-1 day');
        $minusTwo = $today->modify('-2 days');
        $minusThree = $today->modify('-3 days');
        $sameDayFirst = $repository->findOrCreate($project, $today);
        $sameDaySecond = $repository->findOrCreate($project, $today->setTime(8, 0));
        self::assertSame($sameDayFirst, $sameDaySecond);

        $otherDay = $repository->findOrCreate($project, $minusOne->setTime(10, 0));
        $otherProjectDay = $repository->findOrCreate($otherProject, $today);
        self::assertNotSame($sameDayFirst, $otherDay);
        self::assertNotSame($sameDayFirst, $otherProjectDay);

        $sameDayFirst->incrementErrorCount(2);
        $otherDay->incrementTransactionCount(3);
        $otherProjectDay->incrementNPlusOneCount(1);
        $em->flush();

        $older = new DailyProjectStat()
            ->setProject($project)
            ->setStatDate($minusTwo->setTime(9, 0));
        $older->incrementErrorCount();
        $oldest = new DailyProjectStat()
            ->setProject($project)
            ->setStatDate($minusThree->setTime(9, 0));
        $otherOlder = new DailyProjectStat()
            ->setProject($otherProject)
            ->setStatDate($minusTwo->setTime(9, 0));
        $em->persist($older);
        $em->persist($oldest);
        $em->persist($otherOlder);
        $em->flush();

        $lastThree = $repository->findLastDays($project, 30);
        self::assertCount(4, $lastThree);
        self::assertSame(
            [
                $minusThree->format('Y-m-d'),
                $minusTwo->format('Y-m-d'),
                $minusOne->format('Y-m-d'),
                $today->format('Y-m-d'),
            ],
            array_map(static fn (DailyProjectStat $stat): string => $stat->getStatDate()->format('Y-m-d'), $lastThree),
        );
        self::assertSame(4, $repository->countLastDays($project, 30));

        $paged = $repository->findLastDaysPage($project, 4, 2, 1);
        self::assertCount(2, $paged);
        self::assertSame(
            [$minusOne->format('Y-m-d'), $minusTwo->format('Y-m-d')],
            array_map(static fn (DailyProjectStat $stat): string => $stat->getStatDate()->format('Y-m-d'), $paged),
        );

        $range = $repository->findInRange(
            $project,
            $minusThree->setTime(21, 0),
            $today->setTime(1, 0),
        );
        self::assertCount(3, $range);
        self::assertSame(
            [
                $minusTwo->format('Y-m-d'),
                $minusOne->format('Y-m-d'),
                $today->format('Y-m-d'),
            ],
            array_map(static fn (DailyProjectStat $stat): string => $stat->getStatDate()->format('Y-m-d'), $range),
        );

        $unsaved = new Project()->setName('Unsaved')->setSlug('unsaved-stats');
        $map = $repository->findLastDaysForProjects([$project, $otherProject, $unsaved], 30);
        self::assertArrayHasKey((int) $project->getId(), $map);
        self::assertArrayHasKey((int) $otherProject->getId(), $map);
        self::assertCount(4, $map[(int) $project->getId()]);
        self::assertCount(2, $map[(int) $otherProject->getId()]);
        self::assertSame([], $repository->findLastDaysForProjects([$unsaved], 30));
    }
}
