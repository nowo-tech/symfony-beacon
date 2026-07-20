<?php

declare(strict_types=1);

namespace App\Performance\Service;

use App\Analytics\Repository\DailyProjectStatRepository;
use App\Performance\Entity\PerfSpan;
use App\Performance\Entity\PerfTransaction;
use App\Performance\Repository\PerfTransactionRepository;
use App\Project\Entity\Project;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Seeds sample performance transactions so operators can explore N+1 UI locally.
 */
final readonly class PerformanceDemoSeeder
{
    public const string NPLUS1_TRANSACTION = 'demo.nplus1.products';
    public const string CLEAN_TRANSACTION = 'demo.checkout.clean';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PerfTransactionRepository $transactionRepository,
        private DailyProjectStatRepository $dailyProjectStatRepository,
        private NPlusOneDetector $nPlusOneDetector,
    ) {
    }

    /**
     * @return bool true when new demo rows were inserted
     */
    public function seedIfEmpty(Project $project): bool
    {
        $existing = $this->transactionRepository->findOneBy([
            'project' => $project,
            'transactionName' => self::NPLUS1_TRANSACTION,
        ]);
        if ($existing instanceof PerfTransaction) {
            return false;
        }

        $receivedAt = new DateTimeImmutable();
        $nPlusOneTx = $this->buildNPlusOneTransaction($project, $receivedAt);
        $cleanTx = $this->buildCleanTransaction($project, $receivedAt);

        $this->entityManager->persist($nPlusOneTx);
        $this->entityManager->persist($cleanTx);

        $stat = $this->dailyProjectStatRepository->findOrCreate($project, $receivedAt);
        $stat->incrementTransactionCount();
        $stat->incrementTransactionCount();
        if ($nPlusOneTx->getNPlusOneCount() > 0) {
            $stat->incrementNPlusOneCount($nPlusOneTx->getNPlusOneCount());
        }

        $this->entityManager->flush();

        return true;
    }

    private function buildNPlusOneTransaction(Project $project, DateTimeImmutable $receivedAt): PerfTransaction
    {
        $spanInputs = [];
        for ($i = 1; $i <= 6; ++$i) {
            $spanInputs[] = [
                'op' => 'db.sql.query',
                'description' => \sprintf('SELECT * FROM product WHERE id = %d', $i),
                'span_id' => 'demo-n1-'.$i,
            ];
        }
        $spanInputs[] = [
            'op' => 'http.client',
            'description' => 'GET https://pricing.example/v1/quote',
            'span_id' => 'demo-n1-http',
        ];

        return $this->buildTransaction(
            $project,
            self::NPLUS1_TRANSACTION,
            $receivedAt,
            240.0,
            $spanInputs,
        );
    }

    private function buildCleanTransaction(Project $project, DateTimeImmutable $receivedAt): PerfTransaction
    {
        return $this->buildTransaction(
            $project,
            self::CLEAN_TRANSACTION,
            $receivedAt,
            42.0,
            [
                [
                    'op' => 'db.sql.query',
                    'description' => 'SELECT COUNT(*) FROM product',
                    'span_id' => 'demo-clean-1',
                ],
                [
                    'op' => 'view.render',
                    'description' => 'twig:product/list',
                    'span_id' => 'demo-clean-2',
                ],
            ],
        );
    }

    /**
     * @param list<array{op: string, description: string, span_id: string}> $spanInputs
     */
    private function buildTransaction(
        Project $project,
        string $name,
        DateTimeImmutable $receivedAt,
        float $durationMs,
        array $spanInputs,
    ): PerfTransaction {
        $detection = $this->nPlusOneDetector->detect($spanInputs);
        $candidateIds = array_fill_keys($detection['candidate_span_ids'], true);

        $tx = new PerfTransaction();
        $tx->setProject($project);
        $tx->setEventId(bin2hex(random_bytes(16)));
        $tx->setTransactionName($name);
        $tx->setReceivedAt($receivedAt);
        $tx->setDurationMs($durationMs);
        $tx->setSpanCount(\count($spanInputs));
        $tx->setNPlusOneCount($detection['count']);
        $tx->setPayload([
            'transaction' => $name,
            'demo' => true,
            'spans' => $spanInputs,
        ]);

        foreach ($spanInputs as $spanData) {
            $span = new PerfSpan();
            $span->setSpanId($spanData['span_id']);
            $span->setOp($spanData['op']);
            $span->setDescription($spanData['description']);
            $span->setDurationMs(8.0);
            $span->setNPlusOneCandidate(isset($candidateIds[$spanData['span_id']]));
            $tx->addSpan($span);
        }

        return $tx;
    }
}
