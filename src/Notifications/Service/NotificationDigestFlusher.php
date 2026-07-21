<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Notifications\Message\DeliverNotificationMessage;
use App\Notifications\Repository\NotificationDigestBufferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Flushes buffered quiet-hours notifications (digest summary or individual messages).
 */
final readonly class NotificationDigestFlusher
{
    public function __construct(
        private NotificationDigestBufferRepository $bufferRepository,
        private QuietHoursEvaluator $quietHoursEvaluator,
        private NotificationPayloadBuilder $payloadBuilder,
        private MessageBusInterface $bus,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{destinations: int, messages: int, skipped_quiet: int}
     */
    public function flush(bool $force = false): array
    {
        $destinations = 0;
        $messages = 0;
        $skippedQuiet = 0;

        foreach ($this->bufferRepository->findDestinationsWithBufferedItems() as $destination) {
            if (!$force && $this->quietHoursEvaluator->isQuietHoursActive($destination)) {
                ++$skippedQuiet;
                continue;
            }

            $rows = $this->bufferRepository->findForDestination($destination);
            if ([] === $rows) {
                continue;
            }

            ++$destinations;
            $destinationId = $destination->getId();
            if (null === $destinationId) {
                continue;
            }

            if ($destination->isDigestEnabled()) {
                $payloads = array_map(static fn ($row) => $row->getPayload(), $rows);
                $project = $destination->getProject();
                if (null === $project) {
                    continue;
                }
                $summary = $this->payloadBuilder->forDigest($project, $destination->getLabel(), $payloads);
                $this->bus->dispatch(new DeliverNotificationMessage($destinationId, $summary));
                ++$messages;
            } else {
                foreach ($rows as $row) {
                    $this->bus->dispatch(new DeliverNotificationMessage($destinationId, $row->getPayload()));
                    ++$messages;
                }
            }

            $this->bufferRepository->removeAll($rows);
        }

        $this->entityManager->flush();

        return [
            'destinations' => $destinations,
            'messages' => $messages,
            'skipped_quiet' => $skippedQuiet,
        ];
    }
}
