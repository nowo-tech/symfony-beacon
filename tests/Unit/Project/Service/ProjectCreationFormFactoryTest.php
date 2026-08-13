<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Service;

use App\Project\Form\ProjectType;
use App\Project\Service\ProjectCreationFormFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

final class ProjectCreationFormFactoryTest extends TestCase
{
    public function testCreateDelegatesToFormFactory(): void
    {
        $form = $this->createStub(FormInterface::class);
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory
            ->expects(self::once())
            ->method('create')
            ->with(ProjectType::class, ['name' => 'Demo'], ['csrf_protection' => false])
            ->willReturn($form);

        $factory = new ProjectCreationFormFactory($formFactory);

        self::assertSame($form, $factory->create(['name' => 'Demo'], ['csrf_protection' => false]));
    }

    public function testCreateUsesEmptyDefaults(): void
    {
        $form = $this->createStub(FormInterface::class);
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory
            ->expects(self::once())
            ->method('create')
            ->with(ProjectType::class, [], [])
            ->willReturn($form);

        self::assertSame($form, new ProjectCreationFormFactory($formFactory)->create());
    }
}
