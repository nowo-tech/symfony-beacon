<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Identity\Entity\User;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Account security: change password.
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
                'attr' => ['autocomplete' => 'current-password', 'class' => 'input'],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => true,
                'first_options' => [
                    'label' => 'user_preferences.plain_password.first.label',
                    'attr' => ['autocomplete' => 'new-password', 'class' => 'input'],
                ],
                'second_options' => [
                    'label' => 'user_preferences.plain_password.second.label',
                    'attr' => ['autocomplete' => 'new-password', 'class' => 'input'],
                ],
                'invalid_message' => 'user_preferences.plain_password.mismatch',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'translation_domain' => 'messages',
        ]);
    }
}
