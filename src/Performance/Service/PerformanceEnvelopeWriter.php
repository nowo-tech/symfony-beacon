<?php

declare(strict_types=1);

namespace App\Performance\Service;

use App\Analytics\Repository\DailyProjectStatRepository;
use App\Performance\Entity\PerfSpan;
use App\Performance\Entity\PerfTransaction;
use App\Project\Entity\Project;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persists Envelope transaction items into PerfTransaction + PerfSpan and bumps daily counters.
 *
 * Called from {@see \App\Ingest\MessageHandler\ProcessEnvelopeHandler}; does not flush
 * or dispatch notifications — the handler owns the single flush and after-flush side effects.
 */
final readonly class PerformanceEnvelopeWriter
{
    /** Hard cap on persisted spans per transaction (payload may still be larger). */
    public const int MAX_SPANS_PER_TRANSACTION = 500;

    public function __construct(
        private NPlusOneDetector $nPlusOneDetector,
        private DailyProjectStatRepository $dailyProjectStatRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function write(
        Project $project,
        array $payload,
        DateTimeImmutable $receivedAt,
    ): PerformanceEnvelopeWriteResult {
        $spansRaw = $payload['spans'] ?? [];
        $spanInputs = [];
        if (\is_array($spansRaw)) {
            foreach ($spansRaw as $span) {
                if (!\is_array($span)) {
                    continue;
                }
                $spanInputs[] = [
                    'op' => (string) ($span['op'] ?? ''),
                    'description' => (string) ($span['description'] ?? ''),
                    'span_id' => (string) ($span['span_id'] ?? ''),
                    'start_timestamp' => $span['start_timestamp'] ?? null,
                    'timestamp' => $span['timestamp'] ?? null,
                ];
                if (\count($spanInputs) >= self::MAX_SPANS_PER_TRANSACTION) {
                    break;
                }
            }
        }

        $detection = $this->nPlusOneDetector->detect($spanInputs);
        $candidateIds = array_fill_keys($detection['candidate_span_ids'], true);

        $tx = new PerfTransaction();
        $tx->setProject($project);
        $tx->setEventId((string) ($payload['event_id'] ?? bin2hex(random_bytes(16))));
        $tx->setTransactionName((string) ($payload['transaction'] ?? $payload['transaction_info']['name'] ?? 'unknown'));
        $tx->setPayload($payload);
        $tx->setReceivedAt($receivedAt);
        $tx->setSpanCount(\count($spanInputs));
        $tx->setNPlusOneCount($detection['count']);

        $start = isset($payload['start_timestamp']) && is_numeric($payload['start_timestamp']) ? (float) $payload['start_timestamp'] : null;
        $end = isset($payload['timestamp']) && is_numeric($payload['timestamp']) ? (float) $payload['timestamp'] : null;
        if (null !== $start && null !== $end) {
            $tx->setDurationMs(max(0, ($end - $start) * 1000));
        }

        foreach ($spanInputs as $spanData) {
            $span = new PerfSpan();
            $span->setSpanId('' !== $spanData['span_id'] ? $spanData['span_id'] : bin2hex(random_bytes(8)));
            $span->setOp($spanData['op']);
            $span->setDescription($spanData['description']);
            $span->setNPlusOneCandidate(isset($candidateIds[$spanData['span_id']]));
            $s = isset($spanData['start_timestamp']) && is_numeric($spanData['start_timestamp']) ? (float) $spanData['start_timestamp'] : null;
            $e = isset($spanData['timestamp']) && is_numeric($spanData['timestamp']) ? (float) $spanData['timestamp'] : null;
            if (null !== $s && null !== $e) {
                $span->setDurationMs(max(0, ($e - $s) * 1000));
            }
            $tx->addSpan($span);
        }

        $this->entityManager->persist($tx);

        $stat = $this->dailyProjectStatRepository->findOrCreate($project, $receivedAt);
        $stat->incrementTransactionCount();
        if ($detection['count'] > 0) {
            $stat->incrementNPlusOneCount($detection['count']);
        }

        return new PerformanceEnvelopeWriteResult(
            transaction: $tx,
            nPlusOneCount: $detection['count'],
        );
    }
}
