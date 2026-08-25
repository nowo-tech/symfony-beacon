<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Service;

use App\Identity\Entity\User;
use App\Identity\Service\AccountTrustedDevices;
use DateTimeImmutable;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Port\TrustedDeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Trust\TrustedDevice;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use PHPUnit\Framework\TestCase;

final class AccountTrustedDevicesTest extends TestCase
{
    public function testIdentifierUsesSecurityUserIdentifier(): void
    {
        $user = new User()->setEmail('ops@example.com');
        $service = new AccountTrustedDevices($this->createStub(TrustedDeviceRepositoryInterface::class));

        self::assertSame('ops@example.com', $service->identifier($user)->value);
        self::assertNull($service->currentDeviceId(null));
    }

    public function testListForMapsActiveTrustsAndMarksCurrent(): void
    {
        $user = new User()->setEmail('ops@example.com');
        $uid = new UserIdentifier('ops@example.com');
        $currentId = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $otherId = '01BX5ZZKBKACTAV9WEVGEMMVRY';

        $repo = $this->createStub(TrustedDeviceRepositoryInterface::class);
        $repo->method('forUser')->willReturn([
            new TrustedDevice(
                new DeviceId($currentId),
                $uid,
                new DateTimeImmutable('2026-08-01T10:00:00+00:00'),
                null,
                null,
                'Mac · Chrome',
            ),
            new TrustedDevice(
                new DeviceId($otherId),
                $uid,
                new DateTimeImmutable('2026-07-01T10:00:00+00:00'),
                null,
                null,
                '',
            ),
        ]);

        $rows = new AccountTrustedDevices($repo)->listFor($user, null);

        self::assertCount(2, $rows);
        self::assertSame($currentId, $rows[0]->deviceId);
        self::assertSame('Mac · Chrome', $rows[0]->label);
        self::assertFalse($rows[0]->current);
        self::assertSame($otherId, $rows[1]->label);
    }
}
