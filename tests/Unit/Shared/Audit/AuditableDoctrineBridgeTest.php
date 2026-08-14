<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Audit;

use App\Identity\Entity\User;
use App\Shared\Audit\AuditableDoctrineBridge;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Nowo\AuditKitBundle\Doctrine\AuditableEntityListener;
use Nowo\AuditKitBundle\Doctrine\AuditablePropertyResolver;
use Nowo\AuditKitBundle\Profile\ProfileRegistry;
use Nowo\AuditKitBundle\Security\CurrentUserResolver;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use stdClass;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class AuditableDoctrineBridgeTest extends TestCase
{
    public function testBridgeDelegatesLifecycleEventsWhenAuditDisabled(): void
    {
        self::expectNotToPerformAssertions();
        $entity = new stdClass();
        $em = $this->createStub(EntityManagerInterface::class);

        $registry = new ProfileRegistry([
            'default' => [
                'enabled' => false,
                'user_class' => User::class,
                'fields' => [
                    'created_at' => 'createdAt',
                    'updated_at' => 'updatedAt',
                    'created_by' => 'createdBy',
                    'updated_by' => 'updatedBy',
                ],
                'timestamp_type' => 'datetime_immutable',
                'blameable' => true,
                'timestampable' => true,
            ],
        ], 'default');

        $listener = new AuditableEntityListener(
            $registry,
            new AuditablePropertyResolver(),
            new CurrentUserResolver($this->createStub(TokenStorageInterface::class)),
            $em,
            $this->createStub(ClockInterface::class),
        );

        $bridge = new AuditableDoctrineBridge($listener);
        $bridge->prePersist(new PrePersistEventArgs($entity, $em));
        $changeSet = [];
        $bridge->preUpdate(new PreUpdateEventArgs($entity, $em, $changeSet));
    }
}
