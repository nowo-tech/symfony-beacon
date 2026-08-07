<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Consume-once cache guard for signed Teams hook action tokens.
 */
final readonly class ActionTokenConsumer
{
    public function __construct(
        private InteractionActionToken $actionToken,
        #[Autowire(service: 'cache.action_token')]
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function consumeOnce(array $payload): ActionTokenConsumeResult
    {
        $cacheKey = $this->actionToken->consumeCacheKey($payload);
        if (null === $cacheKey) {
            return ActionTokenConsumeResult::Invalid;
        }

        $item = $this->cache->getItem($cacheKey);
        if ($item->isHit()) {
            return ActionTokenConsumeResult::AlreadyUsed;
        }

        $item->set(1);
        $ttl = max(1, (int) ($payload['exp'] ?? 0) - time());
        $item->expiresAfter($ttl);
        $this->cache->save($item);

        return ActionTokenConsumeResult::Consumed;
    }
}

enum ActionTokenConsumeResult
{
    case Invalid;
    case AlreadyUsed;
    case Consumed;
}
