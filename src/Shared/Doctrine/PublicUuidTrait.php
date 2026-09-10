<?php

declare(strict_types=1);

namespace App\Shared\Doctrine;

use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
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
     * Operator/preload may force a stable UUID (DSN path in client .env) before flush.
     * Replaces any auto-generated value from {@see ensureUuid()}.
     *
     * @throws InvalidArgumentException when {@code $uuid} is non-empty but not a valid RFC UUID
     */
    public function assignUuid(string $uuid): void
    {
        $uuid = trim($uuid);
        if ('' === $uuid) {
            return;
        }
        if (!Uuid::isValid($uuid)) {
            throw new InvalidArgumentException(\sprintf('invalid_uuid:%s', $uuid));
        }
        $this->uuid = $uuid;
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
