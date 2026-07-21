<?php

declare(strict_types=1);

namespace App\Analytics\Service;

use App\Analytics\Dto\AnalyticsDayPoint;
use App\Analytics\Repository\DailyProjectStatRepository;
use App\Issues\Repository\EventRepository;
use App\Project\Entity\Project;
use DateTimeImmutable;

/**
 * Builds zero-filled daily analytics series for chart + table.
 */
final readonly class AnalyticsSeriesService
{
    public function __construct(
        private DailyProjectStatRepository $dailyProjectStatRepository,
        private EventRepository $eventRepository,
    ) {
    }

    /**
     * @return list<AnalyticsDayPoint> ascending by date
     */
    public function build(
        Project $project,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        ?string $environment,
        ?string $release,
        ?string $level,
    ): array {
        $filtered = $this->hasFilters($environment, $release, $level);
        if ($filtered) {
            $errorsByDay = $this->eventRepository->countErrorsByDay(
                $project,
                $from,
                $to,
                $environment,
                $release,
                $level,
            );

            return $this->zeroFill($from, $to, $errorsByDay, null, null, filtered: true);
        }

        $rows = $this->dailyProjectStatRepository->findInRange($project, $from, $to);
        $errors = [];
        $transactions = [];
        $nplus1 = [];
        foreach ($rows as $row) {
            $key = $row->getStatDate()->format('Y-m-d');
            $errors[$key] = $row->getErrorCount();
            $transactions[$key] = $row->getTransactionCount();
            $nplus1[$key] = $row->getNPlusOneCount();
        }

        return $this->zeroFill($from, $to, $errors, $transactions, $nplus1, filtered: false);
    }

    public function hasFilters(?string $environment, ?string $release, ?string $level): bool
    {
        return (null !== $environment && '' !== $environment)
            || (null !== $release && '' !== $release)
            || (null !== $level && '' !== $level);
    }

    /**
     * @param array<string, int>      $errorsByDay
     * @param array<string, int>|null $transactionsByDay
     * @param array<string, int>|null $nPlusOneByDay
     *
     * @return list<AnalyticsDayPoint>
     */
    private function zeroFill(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        array $errorsByDay,
        ?array $transactionsByDay,
        ?array $nPlusOneByDay,
        bool $filtered,
    ): array {
        $points = [];
        $cursor = $from;
        while ($cursor <= $to) {
            $key = $cursor->format('Y-m-d');
            $points[] = new AnalyticsDayPoint(
                date: $cursor,
                errorCount: $errorsByDay[$key] ?? 0,
                transactionCount: $filtered ? null : ($transactionsByDay[$key] ?? 0),
                nPlusOneCount: $filtered ? null : ($nPlusOneByDay[$key] ?? 0),
            );
            $cursor = $cursor->modify('+1 day');
        }

        return $points;
    }
}
