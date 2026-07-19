<?php

declare(strict_types=1);

namespace App\Project\Entity;

use App\Issues\Entity\Issue;
use App\Project\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'project')]
#[ORM\UniqueConstraint(name: 'uniq_project_slug', columns: ['slug'])]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name = '';

    #[ORM\Column(length: 120)]
    private string $slug = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** @var Collection<int, ProjectApiKey> */
    #[ORM\OneToMany(targetEntity: ProjectApiKey::class, mappedBy: 'project', orphanRemoval: true, cascade: ['persist'])]
    private Collection $apiKeys;

    /** @var Collection<int, ProjectMembership> */
    #[ORM\OneToMany(targetEntity: ProjectMembership::class, mappedBy: 'project', orphanRemoval: true, cascade: ['persist'])]
    private Collection $memberships;

    /** @var Collection<int, Issue> */
    #[ORM\OneToMany(targetEntity: Issue::class, mappedBy: 'project')]
    private Collection $issues;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->apiKeys = new ArrayCollection();
        $this->memberships = new ArrayCollection();
        $this->issues = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = strtolower(trim($slug));

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection<int, ProjectApiKey>
     */
    public function getApiKeys(): Collection
    {
        return $this->apiKeys;
    }

    public function addApiKey(ProjectApiKey $apiKey): self
    {
        if (!$this->apiKeys->contains($apiKey)) {
            $this->apiKeys->add($apiKey);
            $apiKey->setProject($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, ProjectMembership>
     */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function addMembership(ProjectMembership $membership): self
    {
        if (!$this->memberships->contains($membership)) {
            $this->memberships->add($membership);
            $membership->setProject($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Issue>
     */
    public function getIssues(): Collection
    {
        return $this->issues;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
