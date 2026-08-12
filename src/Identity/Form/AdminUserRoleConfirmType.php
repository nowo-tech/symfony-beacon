<?php

declare(strict_types=1);

namespace App\Identity\Form;

use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Confirms an instance role change for an existing user.
 */
final class AdminUserRoleConfirmType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addChoiceField('role', [
                'required' => true,
                'label' => false,
                'choices' => [
                    'users.role.user' => 'user',
                    'users.role.admin' => 'admin',
                ],
                'choice_translation_domain' => 'messages',
                'placeholder' => false,
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'admin_user_role',
            'translation_domain' => 'messages',
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return '';
    }
}
