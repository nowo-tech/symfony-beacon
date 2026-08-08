<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Identity\Entity\User;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\PasswordStrengthBundle\Form\PasswordStrengthType;
use Nowo\PasswordStrengthBundle\Validator\PasswordStrength;
use Nowo\PasswordToggleBundle\Form\Type\PasswordType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Account security: change password with PasswordToggle, PasswordStrength (strong),
 * and password generator modal (nowo-tech/password-strength-bundle) — FormKit.
 */
final class AccountSecurityType extends FormKitAbstractType
{
    public function __construct(
        FormOptionsMerger $formOptionsMerger,
        FormTypeMap $formTypeMap,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct($formOptionsMerger, $formTypeMap);
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->boundBuilder()->add(
                'currentPassword',
                PasswordType::class,
                $this->mergeFieldOptions('currentPassword', 'password', [
                    'mapped' => false,
                    'required' => true,
                    'label' => 'user_preferences.current_password.label',
                    'help' => 'user_preferences.current_password.help_required',
                    'translation_domain' => 'messages',
                    'attr' => ['autocomplete' => 'current-password'],
                    'constraints' => [
                        new NotBlank(message: 'preferences.error.current_password'),
                    ],
                ]),
            );
            $this->boundBuilder()->add(
                'plainPassword',
                PasswordStrengthType::class,
                $this->mergeFieldOptions('plainPassword', 'password', [
                    'mapped' => true,
                    'required' => true,
                    // Label from messages; strength/generator catalogue stays on the kit domain.
                    'label' => $this->translator->trans('user_preferences.plain_password.first.label', [], 'messages'),
                    'translation_domain' => 'NowoPasswordStrengthBundle',
                    'attr' => ['autocomplete' => 'new-password'],
                    'level' => 'strong',
                    'policy_mode' => 'level',
                    'ui_framework' => 'default',
                    'use_password_toggle' => true,
                    'generator_mode' => 'modal',
                    'generator_count' => 3,
                    'constraints' => [
                        new NotBlank(message: 'preferences.error.password_required'),
                        $this->strongPasswordConstraint(),
                    ],
                ]),
            );
            $this->boundBuilder()->add(
                'plainPassword_confirm',
                PasswordType::class,
                $this->mergeFieldOptions('plainPassword_confirm', 'password', [
                    'mapped' => false,
                    'required' => true,
                    'label' => 'user_preferences.plain_password.second.label',
                    'translation_domain' => 'messages',
                    'attr' => ['autocomplete' => 'new-password'],
                    'constraints' => [
                        new NotBlank(message: 'preferences.error.password_required'),
                        new EqualTo(
                            propertyPath: 'parent.all[plainPassword].data',
                            message: 'user_preferences.plain_password.mismatch',
                        ),
                    ],
                ]),
            );
        });
    }

    private function strongPasswordConstraint(): PasswordStrength
    {
        $constraint = new PasswordStrength();
        $constraint->policyMode = 'level';
        $constraint->level = 'strong';
        $constraint->message = 'user_preferences.plain_password.strength_invalid';

        return $constraint;
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'translation_domain' => 'messages',
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'user_preferences';
    }
}
