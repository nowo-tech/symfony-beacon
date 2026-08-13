<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Generic typed confirmation field for confirm dialogs that still need CSRF protection.
 *
 * Empty block prefix keeps POST field names flat ({@code confirmation} / {@code _token}).
 * Twig supplies the visible confirm-dialog label; attrs for Stimulus live on the Type.
 */
final class TypeToConfirmType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, mixed> $fieldAttr */
        $fieldAttr = $options['confirmation_attr'];

        $this->withBuilder($builder, function () use ($fieldAttr): void {
            $this->addTextField('confirmation', [
                'required' => false,
                'label' => false,
                'help' => false,
                'placeholder' => false,
                'attr' => $fieldAttr,
                'label_attr' => ['class' => 'confirm-dialog__label'],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'type_to_confirm',
            'confirmation_attr' => [
                'class' => 'input w-full',
                'autocomplete' => 'off',
                'data-confirm-dialog-target' => 'confirmInput',
            ],
        ]);
        $resolver->setAllowedTypes('confirmation_attr', 'array');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return '';
    }
}
