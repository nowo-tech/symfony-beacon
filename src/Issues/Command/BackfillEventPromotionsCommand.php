<?php

declare(strict_types=1);

namespace App\Issues\Command;

use App\Issues\Entity\Event;
use App\Issues\Entity\EventTag;
use App\Issues\Service\EventPayloadPromoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One-shot backfill of promoted request_url + event_tag from existing event.payload.
 *
 * New ingest writes promotions automatically; this command is for historical rows.
 */
#[AsCommand(
    name: 'app:events:backfill-promotions',
    description: 'Backfill event.request_url and event_tag from payload JSON',
)]
final class BackfillEventPromotionsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventPayloadPromoter $payloadPromoter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('batch', null, InputOption::VALUE_REQUIRED, 'Events per batch', '200')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max events to process (0 = all)', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $batchSize = max(1, (int) $input->getOption('batch'));
        $limit = max(0, (int) $input->getOption('limit'));

        $qb = $this->entityManager->createQueryBuilder()
            ->select('e.id')
            ->from(Event::class, 'e')
            ->orderBy('e.id', 'ASC');

        /** @var list<int> $ids */
        $ids = array_map(
            static fn (int|string $id): int => (int) $id,
            $qb->getQuery()->getSingleColumnResult(),
        );
        if ($limit > 0) {
            $ids = \array_slice($ids, 0, $limit);
        }

        $processed = 0;
        $tagged = 0;
        foreach (array_chunk($ids, $batchSize) as $chunk) {
            /** @var list<Event> $events */
            $events = $this->entityManager->createQueryBuilder()
                ->select('e')
                ->from(Event::class, 'e')
                ->andWhere('e.id IN (:ids)')
                ->setParameter('ids', $chunk)
                ->getQuery()
                ->getResult();

            foreach ($events as $event) {
                $payload = $event->getPayload();
                $url = $this->payloadPromoter->extractRequestUrl($payload);
                if (null !== $url && $url !== $event->getRequestUrl()) {
                    $event->setRequestUrl($url);
                }

                $existing = $this->entityManager->createQueryBuilder()
                    ->select('COUNT(t.id)')
                    ->from(EventTag::class, 't')
                    ->andWhere('t.event = :event')
                    ->setParameter('event', $event)
                    ->getQuery()
                    ->getSingleScalarResult();
                if (0 === (int) $existing) {
                    $issue = $event->getIssue();
                    $project = $event->getProject();
                    if (null !== $issue && null !== $project) {
                        foreach ($this->payloadPromoter->extractTags($payload) as $tag) {
                            $eventTag = new EventTag();
                            $eventTag->setEvent($event);
                            $eventTag->setIssue($issue);
                            $eventTag->setProject($project);
                            $eventTag->setTagKey($tag['key']);
                            $eventTag->setTagValue($tag['value']);
                            $this->entityManager->persist($eventTag);
                            ++$tagged;
                        }
                    }
                }
                ++$processed;
            }

            $this->entityManager->flush();
            $this->entityManager->clear();
            $io->writeln(sprintf('Processed %d / %d events…', $processed, \count($ids)));
        }

        $io->success(sprintf('Backfill done: %d events, %d tag rows inserted.', $processed, $tagged));

        return Command::SUCCESS;
    }
}
