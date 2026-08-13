<?php

declare(strict_types=1);

namespace App\Shared\Settings\Entity;

use Doctrine\ORM\Mapping as ORM;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

trait InstanceSettingsMailerFields
{
    /** Encrypted Mailer DSN read by {@see \App\Shared\Mailer\ConfiguredMailer}. */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Encrypted]
    private ?string $mailerDsn = null;

    /** Encrypted Mailer From address read by {@see \App\Shared\Mailer\ConfiguredMailer}. */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Encrypted]
    private ?string $mailerFrom = null;

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

        return '' !== $from ? $from : InstanceSettings::DEFAULT_MAILER_FROM;
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
}
