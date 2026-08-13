<?php

declare(strict_types=1);

namespace App\Project\Form;

use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\EqualTo;

/**
 * Danger-zone delete project form (type-to-confirm + CSRF).
 *
 * Catalogue: {@code translations/form.*.yaml} → {@code project_delete.*}.
 */
final class ProjectDeleteType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $projectId = (int) $options['project_id'];
        $idPrefix = (string) $options['input_id_prefix'];

        $this->withBuilder($builder, function () use ($options, $projectId, $idPrefix): void {
            $this->addTextField('confirmation', [
                'help' => false,
                'placeholder' => false,
                'required' => true,
                'constraints' => [
                    new EqualTo((string) $options['confirmation_value']),
                ],
                'attr' => [
                    'id' => $idPrefix.$projectId,
                    'class' => 'input w-full',
                    'autocomplete' => 'off',
                    'data-confirm-dialog-target' => 'confirmInput',
                    'data-action' => 'input->confirm-dialog#syncSubmit',
                ],
                'label_attr' => ['class' => 'confirm-dialog__label'],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'project_delete',
            'project_id' => 0,
            'confirmation_value' => '',
            'input_id_prefix' => 'project-delete-confirm-',
        ]);
        $resolver->setAllowedTypes('project_id', 'int');
        $resolver->setAllowedTypes('confirmation_value', 'string');
        $resolver->setAllowedTypes('input_id_prefix', 'string');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'project_delete';
    }
}
