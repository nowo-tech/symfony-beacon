<?php

declare(strict_types=1);

namespace App\Shared\Settings\Form;

use App\Shared\Settings\Entity\InstanceSettings;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Nowo\PasswordToggleBundle\Form\Type\PasswordType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Instance Mercure settings (optional live member alerts; URLs + JWT encrypted at rest).
 */
final class InstanceMercureSettingsType extends FormKitAbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addCheckboxField('mercureEnabled', [
                'placeholder' => false,
                'required' => false,
                'label' => 'instance_mercure.enabled.label',
                'help' => 'instance_mercure.enabled.help',
            ]);
            $this->addTextField('mercureUrl', [
                'required' => false,
                'label' => 'instance_mercure.url.label',
                'help' => 'instance_mercure.url.help',
                'attr' => [
                    'placeholder' => 'instance_mercure.url.placeholder',
                ],
                'constraints' => [
                    new Length(max: 2048),
                ],
            ]);
            $this->addTextField('mercurePublicUrl', [
                'required' => false,
                'label' => 'instance_mercure.public_url.label',
                'help' => 'instance_mercure.public_url.help',
                'attr' => [
                    'placeholder' => 'instance_mercure.public_url.placeholder',
                ],
                'constraints' => [
                    new Length(max: 2048),
                ],
            ]);
            $this->boundBuilder()->add(
                'plainMercureJwtSecret',
                PasswordType::class,
                $this->mergeFieldOptions('plainMercureJwtSecret', 'password', [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'instance_mercure.jwt_secret.label',
                    'help' => 'instance_mercure.jwt_secret.help',
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'placeholder' => 'instance_mercure.jwt_secret.placeholder',
                    ],
                    'constraints' => [
                        new Length(max: 512),
                        new Callback($this->validatePlainSecret(...)),
                    ],
                ]),
            );
            $this->addCheckboxField('clearMercureJwtSecret', [
                'placeholder' => false,
                'mapped' => false,
                'required' => false,
                'label' => 'instance_mercure.clear_secret.label',
                'help' => 'instance_mercure.clear_secret.help',
            ]);
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InstanceSettings::class,
            'translation_domain' => 'messages',
        ]);
    }

    public function validatePlainSecret(mixed $value, ExecutionContextInterface $context): void
    {
        if (null === $value || '' === $value) {
            return;
        }
        if (!\is_string($value)) {
            $context->buildViolation('instance_mercure.jwt_secret.invalid')
                ->setTranslationDomain('messages')
                ->addViolation();

            return;
        }
        if (\strlen(trim($value)) < 32) {
            $context->buildViolation('instance_mercure.jwt_secret.too_short')
                ->setTranslationDomain('messages')
                ->addViolation();
        }
    }
}
