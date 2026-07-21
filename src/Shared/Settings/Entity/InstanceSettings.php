<?php

declare(strict_types=1);

namespace App\Shared\Settings\Entity;

use App\Identity\Entity\User;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Doctrine\ORM\Mapping as ORM;
use Nowo\AuditKitBundle\Model\AuditableInterface;
use Nowo\AuditKitBundle\Model\TimestampableTrait;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

/**
 * Singleton row for instance-wide operator settings (ROLE_ADMIN).
 */
#[ORM\Entity(repositoryClass: InstanceSettingsRepository::class)]
#[ORM\Table(name: 'instance_settings')]
class InstanceSettings implements AuditableInterface
{
    use TimestampableTrait;

    public const DEFAULT_MAILER_FROM = 'beacon@localhost';

    #[ORM\Id]
    #[ORM\Column]
    private int $id = 1;

    /** Symfony Mailer DSN (encrypted at rest via doctrine-encrypt-bundle). */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Encrypted]
    private ?string $mailerDsn = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $mailerFrom = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'updated_by_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    public static function defaults(): self
    {
        return new self();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getMailerDsn(): ?string
    {
        return $this->mailerDsn;
    }

    public function setMailerDsn(?string $mailerDsn): self
    {
        if (null === $mailerDsn) {
            $this->mailerDsn = null;

            return $this;
        }

        $trimmed = trim($mailerDsn);
        $this->mailerDsn = '' !== $trimmed ? $trimmed : null;

        return $this;
    }

    public function hasMailerDsn(): bool
    {
        return null !== $this->mailerDsn && '' !== $this->mailerDsn;
    }

    public function maskedMailerDsn(): ?string
    {
        if (!$this->hasMailerDsn()) {
            return null;
        }

        $value = (string) $this->mailerDsn;
        $schemePos = strpos($value, '://');
        if (false === $schemePos) {
            return str_repeat('•', min(12, \strlen($value)));
        }

        $scheme = substr($value, 0, $schemePos + 3);
        $rest = substr($value, $schemePos + 3);
        if ('' === $rest) {
            return $scheme.'••••';
        }

        if (\strlen($rest) <= 8) {
            return $scheme.str_repeat('•', \strlen($rest));
        }

        return $scheme.substr($rest, 0, 2).str_repeat('•', max(4, \strlen($rest) - 6)).substr($rest, -4);
    }

    public function getMailerFrom(): ?string
    {
        return $this->mailerFrom;
    }

    public function getEffectiveMailerFrom(): string
    {
        $from = null !== $this->mailerFrom ? trim($this->mailerFrom) : '';

        return '' !== $from ? $from : self::DEFAULT_MAILER_FROM;
    }

    public function setMailerFrom(?string $mailerFrom): self
    {
        if (null === $mailerFrom) {
            $this->mailerFrom = null;

            return $this;
        }

        $trimmed = trim($mailerFrom);
        $this->mailerFrom = '' !== $trimmed ? $trimmed : null;

        return $this;
    }

    public function getCreatedBy(): ?object
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?object $createdBy): void
    {
        $this->createdBy = $createdBy instanceof User ? $createdBy : null;
    }

    public function getUpdatedBy(): ?object
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?object $updatedBy): void
    {
        $this->updatedBy = $updatedBy instanceof User ? $updatedBy : null;
    }
}
