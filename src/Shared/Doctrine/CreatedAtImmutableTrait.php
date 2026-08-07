<?php

declare(strict_types=1);

namespace App\Shared\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Append-only created timestamp (set once in the constructor).
 */
trait CreatedAtImmutableTrait
{
    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    protected function initializeCreatedAt(): void
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
