<?php

declare(strict_types=1);

namespace App\Shared\Settings\Form;

use App\Shared\Settings\Entity\InstanceSettings;
use Nowo\PasswordToggleBundle\Form\Type\PasswordType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Instance Mercure settings (optional live member alerts; URLs + JWT encrypted at rest).
 *
 * @extends AbstractType<InstanceSettings>
 */
final class InstanceMercureSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('mercureEnabled', CheckboxType::class, [
                'required' => false,
                'label' => 'instance_mercure.enabled.label',
                'help' => 'instance_mercure.enabled.help',
            ])
            ->add('mercureUrl', TextType::class, [
                'required' => false,
                'label' => 'instance_mercure.url.label',
                'help' => 'instance_mercure.url.help',
                'attr' => [
                    'placeholder' => 'http://mercure/.well-known/mercure',
                ],
                'constraints' => [
                    new Length(max: 2048),
                ],
            ])
            ->add('mercurePublicUrl', TextType::class, [
                'required' => false,
                'label' => 'instance_mercure.public_url.label',
                'help' => 'instance_mercure.public_url.help',
                'attr' => [
                    'placeholder' => 'https://localhost:9444/.well-known/mercure',
                ],
                'constraints' => [
                    new Length(max: 2048),
                ],
            ])
            ->add('plainMercureJwtSecret', PasswordType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'instance_mercure.jwt_secret.label',
                'help' => 'instance_mercure.jwt_secret.help',
                'attr' => [
                    'autocomplete' => 'new-password',
                    'placeholder' => '••••••••••••••••••••••••••••••••',
                ],
                'constraints' => [
                    new Length(max: 512),
                    new Callback($this->validatePlainSecret(...)),
                ],
            ])
            ->add('clearMercureJwtSecret', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'instance_mercure.clear_secret.label',
                'help' => 'instance_mercure.clear_secret.help',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InstanceSettings::class,
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
