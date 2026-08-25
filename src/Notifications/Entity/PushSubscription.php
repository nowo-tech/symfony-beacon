<?php

declare(strict_types=1);

namespace App\Notifications\Entity;

use App\Identity\Entity\User;
use App\Notifications\Repository\PushSubscriptionRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nowo\AuditKitBundle\Model\TimestampableTrait;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

/**
 * Browser Web Push subscription for a user (endpoint + VAPID client keys).
 */
#[ORM\Entity(repositoryClass: PushSubscriptionRepository::class)]
#[ORM\Table(name: 'push_subscription')]
#[ORM\UniqueConstraint(name: 'uniq_push_subscription_endpoint_hash', columns: ['endpoint_hash'])]
#[ORM\Index(name: 'idx_push_subscription_user', columns: ['user_id'])]
class PushSubscription
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** SHA-256 hex of the endpoint (unique lookup without decrypting). */
    #[ORM\Column(length: 64)]
    private string $endpointHash = '';

    #[Encrypted]
    #[ORM\Column(type: 'text')]
    private string $endpoint = '';

    #[Encrypted]
    #[ORM\Column(type: 'text')]
    private string $p256dh = '';

    #[Encrypted]
    #[ORM\Column(type: 'text')]
    private string $authToken = '';

    #[ORM\Column(length: 32)]
    private string $contentEncoding = 'aes128gcm';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    public function __construct(#[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user)
    {
        $now = new DateTimeImmutable();
        $this->setCreatedAt($now);
        $this->setUpdatedAt($now);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getEndpointHash(): string
    {
        return $this->endpointHash;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Used by doctrine-encrypt-bundle when writing ciphertext (hash stays from plaintext setSubscription).
     */
    public function setEndpoint(string $endpoint): self
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    public function getP256dh(): string
    {
        return $this->p256dh;
    }

    public function setP256dh(string $p256dh): self
    {
        $this->p256dh = $p256dh;

        return $this;
    }

    public function getAuthToken(): string
    {
        return $this->authToken;
    }

    public function setAuthToken(string $authToken): self
    {
        $this->authToken = $authToken;

        return $this;
    }

    public function getContentEncoding(): string
    {
        return $this->contentEncoding;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setSubscription(
        string $endpoint,
        string $p256dh,
        string $authToken,
        string $contentEncoding = 'aes128gcm',
        ?string $userAgent = null,
    ): self {
        $this->endpoint = $endpoint;
        $this->endpointHash = hash('sha256', $endpoint);
        $this->p256dh = $p256dh;
        $this->authToken = $authToken;
        $this->contentEncoding = '' !== $contentEncoding ? $contentEncoding : 'aes128gcm';
        $this->userAgent = $userAgent;
        $this->setUpdatedAt(new DateTimeImmutable());

        return $this;
    }

    public function touch(): void
    {
        $this->setUpdatedAt(new DateTimeImmutable());
    }
}
