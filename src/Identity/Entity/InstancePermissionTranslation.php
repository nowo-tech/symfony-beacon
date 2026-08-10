<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Locale row for a permission catalog label (REQ-RBAC-008 — Translatable, not JSON).
 */
#[ORM\Entity]
#[ORM\Table(name: 'permission_translation')]
#[ORM\UniqueConstraint(name: 'uniq_permission_translation_locale', columns: ['permission_id', 'locale'])]
class InstancePermissionTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InstancePermission::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'permission_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?InstancePermission $permission = null;

    #[ORM\Column(length: 8)]
    private string $locale = 'en';

    #[ORM\Column(length: 120)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPermission(): ?InstancePermission
    {
        return $this->permission;
    }

    public function setPermission(?InstancePermission $permission): self
    {
        $this->permission = $permission;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = strtolower(trim($locale));

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $trimmed = null !== $description ? trim($description) : null;
        $this->description = (null === $trimmed || '' === $trimmed) ? null : $trimmed;

        return $this;
    }
}
