<?php

declare(strict_types=1);

namespace App\Ops\Service;

use App\Shared\Settings\Service\InstanceOpsDefaults;

/**
 * Lists Ops flags that leave the instance in a fail-open security posture.
 *
 * @phpstan-type PostureItem array{id: string, label_key: string}
 */
final readonly class SecurityPosture
{
    public function __construct(
        private InstanceOpsDefaults $opsDefaults,
    ) {
    }

    public function isWeakened(): bool
    {
        return [] !== $this->weakenedItems();
    }

    /**
     * @return list<PostureItem>
     */
    public function weakenedItems(): array
    {
        $items = [];

        if ($this->opsDefaults->allowPrivateUrls()) {
            $items[] = [
                'id' => 'allow_private_urls',
                'label_key' => 'admin.ops.posture.allow_private_urls',
            ];
        }

        if ($this->opsDefaults->allowAnonymousResolve()) {
            $items[] = [
                'id' => 'allow_anonymous_resolve',
                'label_key' => 'admin.ops.posture.allow_anonymous_resolve',
            ];
        }

        if (!$this->opsDefaults->metricsRequireToken()) {
            $items[] = [
                'id' => 'metrics_require_token_off',
                'label_key' => 'admin.ops.posture.metrics_require_token_off',
            ];
        }

        return $items;
    }
}
