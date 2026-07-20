<?php

declare(strict_types=1);

namespace App\Project\Entity;

use App\Project\Repository\ProjectApiKeyRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Project ingest credential (public/secret key pair) used in Envelope DSN auth.
 */
#[ORM\Entity(repositoryClass: ProjectApiKeyRepository::class)]
#[ORM\Table(name: 'project_api_key')]
#[ORM\UniqueConstraint(name: 'uniq_api_key_public', columns: ['public_key'])]
class ProjectApiKey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'apiKeys')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 64)]
    private string $publicKey = '';

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $secretKey = null;

    #[ORM\Column(length: 80)]
    private string $label = 'Default';

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public static function generate(Project $project, string $label = 'Default', ?string $publicKey = null): self
    {
        $key = new self();
        $key->setProject($project);
        $key->setLabel($label);
        $key->setPublicKey($publicKey ?? bin2hex(random_bytes(16)));
        $key->setSecretKey(bin2hex(random_bytes(16)));

        return $key;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function setPublicKey(string $publicKey): self
    {
        $this->publicKey = $publicKey;

        return $this;
    }

    public function getSecretKey(): ?string
    {
        return $this->secretKey;
    }

    public function setSecretKey(?string $secretKey): self
    {
        $this->secretKey = $secretKey;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Envelope-compatible DSN: https://{public}@{host}/{projectId}.
     */
    public function buildDsn(string $baseUrl): string
    {
        $projectId = $this->project?->getId() ?? 0;
        $host = parse_url(rtrim($baseUrl, '/'), \PHP_URL_HOST) ?: 'localhost';
        $scheme = parse_url(rtrim($baseUrl, '/'), \PHP_URL_SCHEME) ?: 'https';
        $port = parse_url(rtrim($baseUrl, '/'), \PHP_URL_PORT);
        $authority = $host.($port ? ':'.$port : '');

        return \sprintf('%s://%s@%s/%d', $scheme, $this->publicKey, $authority, $projectId);
    }
}
