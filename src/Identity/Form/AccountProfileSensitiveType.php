<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Identity\Entity\User;
use App\Shared\Form\FormKitAbstractType;
use Nowo\PasswordToggleBundle\Form\Type\PasswordType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Account profile sensitive identifiers: email and Slack user ID (current password required).
 */
final class AccountProfileSensitiveType extends FormKitAbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addEmailField('email', [
                'constraints' => [new NotBlank(), new Email(), new Length(max: 180)],
            ]);
            $this->addTextField('slackUserId', [
                'required' => false,
                'constraints' => [new Length(max: 64)],
            ]);
            $this->boundBuilder()->add(
                'currentPassword',
                PasswordType::class,
                $this->mergeFieldOptions('currentPassword', 'password', [
                    'mapped' => false,
                    'required' => true,
                    'help' => 'user_preferences.current_password.help_sensitive_change',
                    'attr' => ['autocomplete' => 'current-password'],
                    'constraints' => [
                        new NotBlank(message: 'preferences.error.current_password'),
                    ],
                ]),
            );
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'user_preferences';
    }
}
