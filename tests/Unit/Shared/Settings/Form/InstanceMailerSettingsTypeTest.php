<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Settings\Form;

use App\Shared\Mailer\MailerDsnValidator;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Form\InstanceMailerSettingsType;
use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\PasswordToggleBundle\Form\Type\PasswordType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class InstanceMailerSettingsTypeTest extends TestCase
{
    public function testBuildsAndValidatesMailerFields(): void
    {
        $form = $this->formFactory()->create(InstanceMailerSettingsType::class, InstanceSettings::defaults());
        $form->submit([
            'plainMailerDsn' => 'null://null',
            'clearMailerDsn' => false,
            'mailerFrom' => 'not-an-email',
        ]);

        self::assertTrue($form->has('plainMailerDsn'));
        self::assertTrue($form->has('clearMailerDsn'));
        self::assertTrue($form->has('mailerFrom'));
        self::assertNotEmpty($form->get('plainMailerDsn')->getErrors());
        self::assertNotEmpty($form->get('mailerFrom')->getErrors());
    }

    public function testValidatePlainDsnRejectsNonStringValuesAndBlockPrefix(): void
    {
        $type = new InstanceMailerSettingsType($this->formOptionsMerger(), new FormTypeMap(), new MailerDsnValidator());
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects(self::once())->method('setTranslationDomain')->with('form')->willReturnSelf();
        $builder->expects(self::once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::once())
            ->method('buildViolation')
            ->with('instance_mailer.plain_mailer_dsn.invalid')
            ->willReturn($builder);

        self::assertSame('instance_mailer', $type->getBlockPrefix());
        $type->validatePlainDsn(1234, $context);
    }

    private function formFactory(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([
                new InstanceMailerSettingsType($this->formOptionsMerger(), new FormTypeMap(), new MailerDsnValidator()),
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
                    'email' => [],
                    'checkbox' => [],
                    'password' => [],
                ],
            ],
        ], 'beacon', new ConstraintDefinitionFactory());
    }
}
