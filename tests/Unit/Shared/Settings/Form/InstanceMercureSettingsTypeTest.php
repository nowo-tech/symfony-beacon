<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Settings\Form;

use App\Shared\Mercure\MercureHubUrlGuard;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Form\InstanceMercureSettingsType;
use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\PasswordToggleBundle\Form\Type\PasswordType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validation;

final class InstanceMercureSettingsTypeTest extends TestCase
{
    public function testBuildsAndValidatesMercureFields(): void
    {
        $form = $this->formFactory()->create(InstanceMercureSettingsType::class, InstanceSettings::defaults());
        $form->submit([
            'mercureEnabled' => true,
            'mercureUrl' => 'ftp://invalid.example.test',
            'mercurePublicUrl' => 'https://metadata.google.internal/.well-known/mercure',
            'plainMercureJwtSecret' => 'too-short',
            'clearMercureJwtSecret' => false,
        ]);

        self::assertTrue($form->has('plainMercureJwtSecret'));
        self::assertNotEmpty($form->get('mercureUrl')->getErrors());
        self::assertNotEmpty($form->get('mercurePublicUrl')->getErrors());
        self::assertNotEmpty($form->get('plainMercureJwtSecret')->getErrors());
    }

    public function testValidatePlainSecretRejectsNonStringValuesAndBlockPrefix(): void
    {
        $type = new InstanceMercureSettingsType($this->formOptionsMerger(), new FormTypeMap(), new MercureHubUrlGuard());
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects(self::once())->method('setTranslationDomain')->with('form')->willReturnSelf();
        $builder->expects(self::once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::once())
            ->method('buildViolation')
            ->with('instance_mercure.plain_mercure_jwt_secret.invalid')
            ->willReturn($builder);

        self::assertSame('instance_mercure', $type->getBlockPrefix());
        $type->validatePlainSecret(1234, $context);
    }

    private function formFactory(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([
                new InstanceMercureSettingsType($this->formOptionsMerger(), new FormTypeMap(), new MercureHubUrlGuard()),
                new PasswordType(),
            ], []))
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();
    }

    private function formOptionsMerger(): FormOptionsMerger
    {
        return new FormOptionsMerger([
            'beacon' => [
                'translation_domain' => 'form',
                'auto_placeholder' => true,
                'auto_help' => true,
                'defaults' => [
                    'attr' => ['class' => 'input'],
                    'row_attr' => ['class' => 'row'],
                ],
                'field_types' => [
                    'text' => [],
                    'checkbox' => [],
                    'password' => [],
                ],
            ],
        ], 'beacon', new ConstraintDefinitionFactory());
    }
}
