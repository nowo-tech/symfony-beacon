<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Identity\Entity\User;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Nowo\PasswordStrengthBundle\Form\PasswordStrengthType;
use Nowo\PasswordStrengthBundle\Validator\PasswordStrength;
use Nowo\PasswordToggleBundle\Form\Type\PasswordType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Account security: change password with PasswordToggle, PasswordStrength (strong),
 * and password generator modal (nowo-tech/password-strength-bundle).
 */
final class AccountSecurityType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'mapped' => false,
                'required' => true,
                'label' => 'user_preferences.current_password.label',
                'help' => 'user_preferences.current_password.help_required',
                'translation_domain' => 'messages',
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [
                    new NotBlank(message: 'preferences.error.current_password'),
                ],
            ])
            ->add('plainPassword', PasswordStrengthType::class, [
                'mapped' => true,
                'required' => true,
                'label' => 'user_preferences.plain_password.first.label',
                'translation_domain' => 'messages',
                'attr' => ['autocomplete' => 'new-password'],
                'level' => 'strong',
                'policy_mode' => 'level',
                'ui_framework' => 'tailwind2',
                'use_password_toggle' => true,
                'generator_mode' => 'modal',
                'generator_count' => 3,
                'constraints' => [
                    new NotBlank(message: 'preferences.error.password_required'),
                    $this->strongPasswordConstraint(),
                ],
            ])
            ->add('plainPassword_confirm', PasswordType::class, [
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
            ]);
    }

    private function strongPasswordConstraint(): PasswordStrength
    {
        $constraint = new PasswordStrength();
        $constraint->policyMode = 'level';
        $constraint->level = 'strong';
        $constraint->message = 'user_preferences.plain_password.strength_invalid';

        return $constraint;
    }

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
