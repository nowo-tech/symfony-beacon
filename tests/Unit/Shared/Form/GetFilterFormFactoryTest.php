<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Form;

use Nowo\FormKitBundle\Form\GetFilterFormFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

final class GetFilterFormFactoryTest extends TestCase
{
    public function testCreateUsesRootlessNamedForm(): void
    {
        $form = $this->createStub(FormInterface::class);
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory
            ->expects(self::once())
            ->method('createNamed')
            ->with('', FormType::class, ['q' => 'x'], ['method' => 'GET'])
            ->willReturn($form);

        $factory = new GetFilterFormFactory($formFactory);

        self::assertSame(
            $form,
            $factory->create(FormType::class, ['q' => 'x'], ['method' => 'GET']),
        );
    }

    public function testCreateUsesEmptyDefaults(): void
    {
        $form = $this->createStub(FormInterface::class);
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory
            ->expects(self::once())
            ->method('createNamed')
            ->with('', FormType::class, [], [])
            ->willReturn($form);

        self::assertSame($form, new GetFilterFormFactory($formFactory)->create(FormType::class));
    }
}
