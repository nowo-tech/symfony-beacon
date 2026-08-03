<?php

declare(strict_types=1);

namespace App\Identity\Form;

use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Admin create-user form (no data_class — maps to {@see User} in the controller).
 */
final class AdminUserType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addEmailField('email', [
                'label' => 'users.form.email',
                'constraints' => [new NotBlank(), new Email(), new Length(max: 180)],
            ]);
            $this->addTextField('displayName', [
                'label' => 'users.form.display_name',
                'constraints' => [new NotBlank(), new Length(max: 120)],
            ]);
            $this->addPasswordField('password', [
                'label' => 'users.form.password',
                'help' => 'users.form.password_help',
                'constraints' => [new NotBlank(), new Length(min: 8, max: 4096)],
            ]);
            $this->addChoiceField('role', [
                'label' => 'users.role_label',
                'choices' => [
                    'users.role.user' => 'user',
                    'users.role.admin' => 'admin',
                ],
                'constraints' => [new NotBlank()],
            ]);
            $this->addCheckboxField('enabled', [
                'label' => 'users.form.enabled',
                'required' => false,
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'admin_user_new',
            'translation_domain' => 'messages',
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'admin_user';
    }
}
