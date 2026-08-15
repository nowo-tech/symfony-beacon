<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Form;

use Nowo\FormKitBundle\Form\CsrfOnlyFormFactory;
use Nowo\FormKitBundle\Form\Type\CsrfOnlyType;
use Nowo\FormKitBundle\Form\Type\HiddenFieldsCsrfType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

final class CsrfOnlyFormFactoryTest extends TestCase
{
    public function testCreateNamedForm(): void
    {
        $form = $this->createStub(FormInterface::class);
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory
            ->expects(self::once())
            ->method('createNamed')
            ->with('csrf_only', CsrfOnlyType::class, null, [
                'action' => '/delete',
                'method' => 'POST',
                'csrf_token_id' => 'project_delete',
                'csrf_field_name' => '_token',
            ])
            ->willReturn($form);

        self::assertSame(
            $form,
            new CsrfOnlyFormFactory($formFactory)->createNamed('/delete', 'project_delete'),
        );
    }

    public function testCreateUnnamedNormalizesMethodAndCustomField(): void
    {
        $form = $this->createStub(FormInterface::class);
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory
            ->expects(self::once())
            ->method('create')
            ->with(CsrfOnlyType::class, null, [
                'action' => '/kit',
                'method' => 'DELETE',
                'csrf_token_id' => 'kit',
                'csrf_field_name' => '_csrf_token',
            ])
            ->willReturn($form);

        self::assertSame(
            $form,
            new CsrfOnlyFormFactory($formFactory)->create(
                '/kit',
                'kit',
                ' delete ',
                '_csrf_token',
            ),
        );
    }

    public function testCreateWithFieldsCastsNullAndPassesOptions(): void
    {
        $form = $this->createStub(FormInterface::class);
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory
            ->expects(self::once())
            ->method('create')
            ->with(
                HiddenFieldsCsrfType::class,
                ['uuid' => 'abc', 'flag' => ''],
                [
                    'action' => '/action',
                    'method' => 'POST',
                    'csrf_token_id' => 'csrf',
                    'csrf_field_name' => '_token',
                    'fields' => ['uuid', 'flag'],
                    'field_types' => ['uuid' => 'hidden'],
                    'field_options' => ['uuid' => ['attr' => ['readonly' => true]]],
                ],
            )
            ->willReturn($form);

        self::assertSame(
            $form,
            new CsrfOnlyFormFactory($formFactory)->createWithFields(
                '/action',
                'csrf',
                ['uuid' => 'abc', 'flag' => null],
                fieldTypes: ['uuid' => 'hidden'],
                fieldOptions: ['uuid' => ['attr' => ['readonly' => true]]],
            ),
        );
    }
}
