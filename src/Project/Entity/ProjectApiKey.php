<?php

declare(strict_types=1);

namespace App\Project\Entity;

use App\Project\Repository\ProjectApiKeyRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

/**
 * Project ingest credential (public/secret key pair) used in Envelope DSN auth.
 *
 * The public key is an opaque, non-secret identifier (shown in Settings / DSN).
 * The secret is shown once after create/rotate (session flash + temporary reveal);
 * at rest only a SHA-256 hash is kept ({@see $secretHash}). Legacy rows may still
 * hold an encrypted {@see $secretKey} until the next successful ingest or rotate
 * upgrades them — Settings never re-derives a DSN from that column.
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

    /** SHA-256 hex of the Envelope secret (preferred at-rest form). */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $secretHash = null;

    /**
     * Legacy encrypted plaintext secret (Halite). Cleared once {@see $secretHash} is set.
     *
     * @deprecated prefer {@see $secretHash}; retained for dual-read migration
     */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Encrypted]
    private ?string $secretKey = null;

    /** In-memory plaintext after generate/rotate only — never persisted. */
    private ?string $issuedPlainSecret = null;

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

    public static function generate(
        Project $project,
        string $label = 'Default',
        ?string $publicKey = null,
        ?string $secretKey = null,
    ): self {
        $key = new self();
        $key->setProject($project);
        $key->setLabel($label);
        $key->setPublicKey($publicKey ?? bin2hex(random_bytes(16)));
        $key->assignPlainSecret($secretKey ?? bin2hex(random_bytes(16)));

        return $key;
    }

    public static function hashSecret(string $plainSecret): string
    {
        return hash('sha256', $plainSecret);
    }

    /**
     * Store SHA-256 at rest and keep plaintext only in memory for one-shot DSN display.
     */
    public function assignPlainSecret(string $plainSecret): self
    {
        $this->issuedPlainSecret = $plainSecret;
        $this->secretHash = self::hashSecret($plainSecret);
        $this->secretKey = null;

        return $this;
    }

    public function matchesSecret(string $plainSecret): bool
    {
        if (null !== $this->secretHash && '' !== $this->secretHash) {
            return hash_equals($this->secretHash, self::hashSecret($plainSecret));
        }

        // Legacy encrypted plaintext column.
        if (null !== $this->secretKey && '' !== $this->secretKey) {
            return hash_equals($this->secretKey, $plainSecret);
        }

        return false;
    }

    /**
     * When auth succeeds against a legacy encrypted secret, upgrade to hash-at-rest.
     */
    public function upgradeLegacySecretToHash(string $plainSecret): bool
    {
        if (null !== $this->secretHash && '' !== $this->secretHash) {
            return false;
        }
        if (null === $this->secretKey || '' === $this->secretKey) {
            return false;
        }
        if (!hash_equals($this->secretKey, $plainSecret)) {
            return false;
        }

        $this->secretHash = self::hashSecret($plainSecret);
        $this->secretKey = null;

        return true;
    }

    /**
     * One-shot plaintext for DSN flash after create/rotate (clears after read).
     */
    public function consumeIssuedPlainSecret(): ?string
    {
        $plain = $this->issuedPlainSecret;
        $this->issuedPlainSecret = null;

        return $plain;
    }

    public function peekIssuedPlainSecret(): ?string
    {
        return $this->issuedPlainSecret;
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

    public function getSecretHash(): ?string
    {
        return $this->secretHash;
    }

    /**
     * Legacy encrypted column only — used by doctrine-encrypt-bundle via PropertyAccess.
     * Do not use for DSN/auth; prefer {@see matchesSecret()} / {@see consumeIssuedPlainSecret()}.
     */
    public function getSecretKey(): ?string
    {
        return $this->secretKey;
    }

    /**
     * Legacy encrypted column only — must not hash or touch {@see $secretHash}/{@see $issuedPlainSecret}.
     * Encrypt subscriber get/set goes through these accessors; routing plaintext here into
     * {@see assignPlainSecret()} would hash ciphertext and break auth.
     */
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
     * Envelope-compatible DSN: https://{public}:{secret}@{host}/{projectUuid}.
     *
     * Pass the plaintext secret (from {@see consumeIssuedPlainSecret()} or a known demo secret).
     * Path uses the public project UUID (numeric id remains accepted on ingest for back-compat).
     */
    public function buildDsn(string $baseUrl, ?string $plainSecret = null): string
    {
        $secret = $plainSecret ?? $this->issuedPlainSecret;
        $projectUuid = $this->project?->getUuid() ?? '';
        $host = parse_url(rtrim($baseUrl, '/'), \PHP_URL_HOST) ?: 'localhost';
        $scheme = parse_url(rtrim($baseUrl, '/'), \PHP_URL_SCHEME) ?: 'https';
        $port = parse_url(rtrim($baseUrl, '/'), \PHP_URL_PORT);
        $authority = $host.($port ? ':'.$port : '');
        $userinfo = $this->publicKey;
        if (null !== $secret && '' !== $secret) {
            $userinfo .= ':'.$secret;
        }

        return \sprintf('%s://%s@%s/%s', $scheme, $userinfo, $authority, $projectUuid);
    }

    /**
     * Mask the secret segment of an Envelope DSN for Settings display.
     *
     * Example: {@code https://pk:secret@host/uuid} → {@code https://pk:••••••••@host/uuid}.
     */
    public static function maskDsn(string $dsn): string
    {
        $masked = preg_replace('#(://[^:/@]+:)[^@]+(@)#', '$1••••••••$2', $dsn);

        return \is_string($masked) ? $masked : $dsn;
    }
}
