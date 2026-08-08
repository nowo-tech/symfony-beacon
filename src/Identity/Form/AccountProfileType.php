<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Identity\Entity\User;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Nowo\PasswordToggleBundle\Form\Type\PasswordType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Account profile: display name and email (FormKit).
 */
final class AccountProfileType extends FormKitAbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addTextField('displayName', [
                'constraints' => [new NotBlank(), new Length(max: 120)],
            ]);
            $this->addEmailField('email', [
                'constraints' => [new NotBlank(), new Email(), new Length(max: 180)],
            ]);
            $this->boundBuilder()->add(
                'currentPassword',
                PasswordType::class,
                $this->mergeFieldOptions('currentPassword', 'password', [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'user_preferences.current_password.label',
                    'help' => 'user_preferences.current_password.help_email_change',
                    'translation_domain' => 'messages',
                    'attr' => ['autocomplete' => 'current-password'],
                ]),
            );
            $this->addTextField('slackUserId', [
                'required' => false,
                'label' => 'preferences.profile.slack_user_id',
                'help' => 'preferences.profile.slack_user_id_help',
                'constraints' => [new Length(max: 64)],
            ]);
            $this->addTextField('phone', [
                'required' => false,
                'label' => 'preferences.profile.phone',
                'help' => 'preferences.profile.phone_help',
                'constraints' => [new Length(max: 32)],
            ]);
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
