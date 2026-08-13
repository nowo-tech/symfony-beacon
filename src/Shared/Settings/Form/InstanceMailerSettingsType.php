<?php

declare(strict_types=1);

namespace App\Shared\Settings\Form;

use App\Shared\Form\FormKitAbstractType;
use App\Shared\Mailer\MailerDsnValidator;
use App\Shared\Settings\Entity\InstanceSettings;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\PasswordToggleBundle\Form\Type\PasswordType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Instance Mailer settings (DSN and From stored encrypted; blank DSN keeps current value).
 */
final class InstanceMailerSettingsType extends FormKitAbstractType
{
    public function __construct(
        FormOptionsMerger $formOptionsMerger,
        FormTypeMap $formTypeMap,
        private readonly MailerDsnValidator $dsnValidator,
    ) {
        parent::__construct($formOptionsMerger, $formTypeMap);
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->boundBuilder()->add(
                'plainMailerDsn',
                PasswordType::class,
                $this->mergeFieldOptions('plainMailerDsn', 'password', [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'instance_mailer.mailer_dsn.label',
                    'help' => 'instance_mailer.mailer_dsn.help',
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'placeholder' => 'instance_mailer.mailer_dsn.placeholder',
                    ],
                    'constraints' => [
                        new Length(max: 2048),
                        new Callback($this->validatePlainDsn(...)),
                    ],
                ]),
            );
            $this->addCheckboxField('clearMailerDsn', [
                'placeholder' => false,
                'mapped' => false,
                'required' => false,
                'label' => 'instance_mailer.clear_dsn.label',
                'help' => 'instance_mailer.clear_dsn.help',
            ]);
            $this->addEmailField('mailerFrom', [
                'required' => false,
                'label' => 'instance_mailer.mailer_from.label',
                'help' => 'instance_mailer.mailer_from.help',
                'attr' => [
                    'placeholder' => 'instance_mailer.mailer_from.placeholder',
                ],
                'constraints' => [
                    new Email(),
                    new Length(max: 180),
                ],
            ]);
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InstanceSettings::class,
        ]);
    }

    public function validatePlainDsn(mixed $value, ExecutionContextInterface $context): void
    {
        if (!\is_string($value) && null !== $value) {
            $context->buildViolation('instance_mailer.mailer_dsn.invalid')
                ->setTranslationDomain('form')
                ->addViolation();

            return;
        }

        $error = $this->dsnValidator->validatePlainDsn($value ?? '');
        if (null !== $error) {
            $context->buildViolation($error)
                ->setTranslationDomain('form')
                ->addViolation();
        }
    }
}
