<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Identity\Entity\User;
use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Account profile basics: display name and phone (no password).
 *
 * Sensitive login identifiers live on {@see AccountProfileSensitiveType}.
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
            $this->addTextField('phone', [
                'required' => false,
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
