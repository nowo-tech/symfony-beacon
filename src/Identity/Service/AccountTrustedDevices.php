<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Dto\AccountTrustedDeviceRow;
use App\Identity\Entity\User;
use DateTimeImmutable;
use Nowo\DeviceIntelligence\Port\TrustedDeviceRepositoryInterface;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;

/**
 * Lists explicit Device Intelligence trust grants for the signed-in user.
 *
 * Device ID is not a credential. Login never auto-trusts.
 */
final readonly class AccountTrustedDevices
{
    public function __construct(
        private TrustedDeviceRepositoryInterface $trustedDevices,
    ) {
    }

    public function identifier(User $user): UserIdentifier
    {
        return new UserIdentifier($user->getUserIdentifier());
    }

    public function currentDeviceId(?DeviceContext $device): ?string
    {
        return $device?->device()->id->value;
    }

    /**
     * @return list<AccountTrustedDeviceRow>
     */
    public function listFor(User $user, ?DeviceContext $device): array
    {
        $currentId = $this->currentDeviceId($device);
        $rows = [];
        foreach ($this->trustedDevices->forUser($this->identifier($user), new DateTimeImmutable()) as $trust) {
            $label = '' !== $trust->label ? $trust->label : $trust->deviceId->value;
            $rows[] = new AccountTrustedDeviceRow(
                $trust->deviceId->value,
                $label,
                $trust->trustedAt,
                $currentId === $trust->deviceId->value,
            );
        }

        return $rows;
    }
}
