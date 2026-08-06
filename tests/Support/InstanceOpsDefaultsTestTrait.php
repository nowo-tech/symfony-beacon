<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;

/**
 * Builds {@see InstanceOpsDefaults} stubs for unit tests.
 */
trait InstanceOpsDefaultsTestTrait
{
    protected function opsDefaultsWith(callable $configure): InstanceOpsDefaults
    {
        $settings = InstanceSettings::defaults();
        $configure($settings);
        $repository = $this->createStub(InstanceSettingsRepository::class);
        $repository->method('getOrCreate')->willReturn($settings);

        return new InstanceOpsDefaults($repository);
    }
}
