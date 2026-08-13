<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Admin create-user form (no data_class — maps to {@see User} in the controller).
 *
 * Field label / placeholder / help use FormKit convention keys in the {@code form}
 * domain ({@code admin_user.<field>.label|placeholder|help} → translations/form.*.yaml).
 */
final class AdminUserType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addEmailField('email', [
                'constraints' => [new NotBlank(), new Email(), new Length(max: 180)],
            ]);
            $this->addTextField('displayName', [
                'constraints' => [new NotBlank(), new Length(max: 120)],
            ]);
            $this->addPasswordField('password', [
                'constraints' => [new NotBlank(), new Length(min: 8, max: 4096)],
            ]);
            $this->addChoiceField('role', [
                'choices' => [
                    'users.role.user' => 'user',
                    'users.role.admin' => 'admin',
                ],
                'constraints' => [new NotBlank()],
            ]);
            $this->addCheckboxField('enabled', [
                'required' => false,
                'placeholder' => false,
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'admin_user_new',
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'admin_user';
    }
}
