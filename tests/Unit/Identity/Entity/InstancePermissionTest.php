<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Entity;

use App\Identity\Entity\InstancePermission;
use App\Identity\Entity\InstancePermissionTranslation;
use App\Identity\Entity\User;
use PHPUnit\Framework\TestCase;
use stdClass;

final class InstancePermissionTest extends TestCase
{
    public function testNormalizesScalarFieldsAndAuditUsers(): void
    {
        $user = new User();
        $permission = new InstancePermission()
            ->setKey(' Project.View ')
            ->setName(' View project ')
            ->setDescription(' description ')
            ->setCategory(' Access ')
            ->setSystem(true);

        $permission->setCreatedBy($user);
        $permission->setUpdatedBy(new stdClass());

        self::assertSame('project.view', $permission->getKey());
        self::assertSame('View project', $permission->getName());
        self::assertSame('description', $permission->getDescription());
        self::assertSame('access', $permission->getCategory());
        self::assertTrue($permission->isSystem());
        self::assertSame($user, $permission->getCreatedBy());
        self::assertNull($permission->getUpdatedBy());

        $permission->setDescription('   ');
        self::assertNull($permission->getDescription());
    }

    public function testManagesTranslationsAndLocaleLookups(): void
    {
        $permission = new InstancePermission();
        $existing = new InstancePermissionTranslation()
            ->setLocale(' ES ')
            ->setName(' Nombre ')
            ->setDescription(' Descripcion ');

        $permission->addTranslation($existing);
        $permission->addTranslation($existing);

        self::assertSame($existing, $permission->findTranslation('es'));
        self::assertSame('Nombre', $permission->getNameForLocale('es'));
        self::assertSame('Descripcion', $permission->getDescriptionForLocale('es'));
        self::assertSame([], $permission->getRoles()->toArray());
        self::assertSame([$existing], $permission->getTranslations()->toArray());

        $permission->syncTranslations(
            ['es' => 'Nombre actualizado', 'fr' => ' '],
            ['es' => '', 'de' => ' Beschreibung '],
        );

        self::assertSame('Nombre actualizado', $permission->getNameForLocale('es'));
        self::assertNull($permission->getDescriptionForLocale('es'));
        self::assertNull($permission->getNameForLocale('de'));
        self::assertSame('Beschreibung', $permission->getDescriptionForLocale('de'));
        self::assertNull($permission->findTranslation('fr'));
        self::assertNull($permission->findTranslation('ignored'));

        $permission->removeTranslation($permission->findTranslation('de'));
        self::assertNull($permission->findTranslation('de'));

        $permission->syncTranslations(['de' => 'Deutsch'], []);
        self::assertNull($permission->findTranslation('es'));
    }
}
