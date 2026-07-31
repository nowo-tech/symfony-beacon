<?php

declare(strict_types=1);

namespace App\Issues\Repository;

use App\Issues\Entity\InboundEmailMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InboundEmailMessage>
 */
class InboundEmailMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InboundEmailMessage::class);
    }

    public function findOneByMessageId(string $messageId): ?InboundEmailMessage
    {
        return $this->findOneBy(['messageId' => $messageId]);
    }
}
