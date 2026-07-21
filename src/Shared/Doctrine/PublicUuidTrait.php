<?php

declare(strict_types=1);

namespace App\Shared\Doctrine;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Public opaque identifier for URL routing (integer PKs stay internal).
 */
trait PublicUuidTrait
{
    #[ORM\Column(length: 36)]
    private string $uuid = '';

    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * Assign a new UUID v7 when missing (constructors / prePersist).
     */
    public function ensureUuid(): void
    {
        if ('' === $this->uuid) {
            $this->uuid = Uuid::v7()->toRfc4122();
        }
    }
}
