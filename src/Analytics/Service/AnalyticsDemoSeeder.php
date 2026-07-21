<?php

declare(strict_types=1);

namespace App\Analytics\Service;

use App\Analytics\Repository\DailyProjectStatRepository;
use App\Project\Entity\Project;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Seeds multi-day daily stats so the Analytics UI has a readable local demo.
 */
final readonly class AnalyticsDemoSeeder
{
    private const int DEFAULT_DAYS = 14;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DailyProjectStatRepository $dailyProjectStatRepository,
    ) {
    }

    /**
     * Fills missing days in the last {@see DEFAULT_DAYS} window (does not overwrite existing rows).
     *
     * @return bool true when at least one day was inserted
     */
    public function seedIfEmpty(Project $project): bool
    {
        return $this->seedWindow($project, self::DEFAULT_DAYS);
    }

    /**
     * Fills missing days in the last $days window (does not overwrite existing rows).
     *
     * @return bool true when at least one day was inserted
     */
    public function seedWindow(Project $project, int $days): bool
    {
        $days = max(1, $days);
        $created = false;
        $today = new DateTimeImmutable('today');

        for ($i = $days - 1; $i >= 0; --$i) {
            $day = $today->modify(\sprintf('-%d days', $i))->setTime(0, 0);
            $existing = $this->dailyProjectStatRepository->findOneBy([
                'project' => $project,
                'statDate' => $day,
            ]);
            if (null !== $existing) {
                continue;
            }

            $stat = $this->dailyProjectStatRepository->findOrCreate($project, $day);
            [$errors, $transactions, $nPlusOne] = $this->demoCountsForDay($i);
            $stat->incrementErrorCount($errors);
            $stat->incrementTransactionCount($transactions);
            if ($nPlusOne > 0) {
                $stat->incrementNPlusOneCount($nPlusOne);
            }
            $created = true;

            if (0 === $i % 30) {
                $this->entityManager->flush();
            }
        }

        if ($created) {
            $this->entityManager->flush();
        }

        return $created;
    }

    /**
     * Deterministic demo curve (weekend dip, mid-week spike, occasional N+1).
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function demoCountsForDay(int $daysAgo): array
    {
        $weekday = (int) new DateTimeImmutable('today')->modify(\sprintf('-%d days', $daysAgo))->format('N');
        $weekend = $weekday >= 6;

        $errors = $weekend ? 2 + ($daysAgo % 3) : 5 + ($daysAgo % 7) * 2;
        $transactions = $weekend ? 1 + ($daysAgo % 2) : 3 + ($daysAgo % 5);
        $nPlusOne = 0 === $daysAgo % 4 ? 1 + ($daysAgo % 2) : 0;

        return [$errors, $transactions, $nPlusOne];
    }
}
