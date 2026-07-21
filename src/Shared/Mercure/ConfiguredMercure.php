<?php

declare(strict_types=1);

namespace App\Shared\Mercure;

use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use RuntimeException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\Jwt\FactoryTokenProvider;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Mercure\Update;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Optional Mercure hub driven by Administration → Mercure (env fallbacks for URL/secret).
 */
final class ConfiguredMercure implements ResetInterface
{
    private ?InstanceSettings $settings = null;
    private ?Hub $hub = null;

    public function __construct(
        private readonly InstanceSettingsRepository $settingsRepository,
        private readonly string $envUrl,
        private readonly string $envPublicUrl,
        private readonly string $envJwtSecret,
        private readonly ?HttpClientInterface $httpClient = null,
    ) {
    }

    public function reset(): void
    {
        $this->settings = null;
        $this->hub = null;
    }

    public function isEnabled(): bool
    {
        if (!$this->settings()->isMercureEnabled()) {
            return false;
        }

        return '' !== $this->resolvedUrl() && '' !== $this->resolvedJwtSecret();
    }

    public function getPublicUrl(): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $public = $this->resolvedPublicUrl();

        return '' !== $public ? $public : $this->resolvedUrl();
    }

    /**
     * @param list<string> $topics
     */
    public function createSubscriberToken(array $topics): ?string
    {
        if (!$this->isEnabled() || [] === $topics) {
            return null;
        }

        $secret = $this->resolvedJwtSecret();
        if ('' === $secret) {
            return null;
        }

        /** @var non-empty-string $secret */
        $factory = new LcobucciFactory($secret);

        return $factory->create($topics, []);
    }

    public function publish(Update $update): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->hub()->publish($update);
    }

    public function isUsingDatabaseSecret(): bool
    {
        return $this->settings()->hasMercureJwtSecret();
    }

    public function isUsingDatabaseUrl(): bool
    {
        return null !== $this->settings()->getMercureUrl() && '' !== $this->settings()->getMercureUrl();
    }

    public function envUrlConfigured(): bool
    {
        return '' !== trim($this->envUrl);
    }

    public function envJwtConfigured(): bool
    {
        return '' !== trim($this->envJwtSecret);
    }

    private function hub(): Hub
    {
        if ($this->hub instanceof Hub) {
            return $this->hub;
        }

        $secret = $this->resolvedJwtSecret();
        $url = $this->resolvedUrl();
        if ('' === $secret || '' === $url) {
            throw new RuntimeException('Mercure is not configured.');
        }

        /** @var non-empty-string $secret */
        $factory = new LcobucciFactory($secret);
        $provider = new FactoryTokenProvider($factory, [], ['*']);
        $public = $this->resolvedPublicUrl();
        $client = $this->httpClient ?? HttpClient::create();

        $this->hub = new Hub(
            $url,
            $provider,
            $factory,
            '' !== $public ? $public : null,
            $client,
        );

        return $this->hub;
    }

    private function settings(): InstanceSettings
    {
        return $this->settings ??= $this->settingsRepository->getOrCreate();
    }

    private function resolvedUrl(): string
    {
        $fromDb = $this->settings()->getMercureUrl();
        if (\is_string($fromDb) && '' !== trim($fromDb)) {
            return trim($fromDb);
        }

        return trim($this->envUrl);
    }

    private function resolvedPublicUrl(): string
    {
        $fromDb = $this->settings()->getMercurePublicUrl();
        if (\is_string($fromDb) && '' !== trim($fromDb)) {
            return trim($fromDb);
        }

        return trim($this->envPublicUrl);
    }

    private function resolvedJwtSecret(): string
    {
        $fromDb = $this->settings()->getMercureJwtSecret();
        if (\is_string($fromDb) && '' !== trim($fromDb)) {
            return trim($fromDb);
        }

        return trim($this->envJwtSecret);
    }
}
