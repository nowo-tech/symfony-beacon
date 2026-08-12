<?php

declare(strict_types=1);

namespace App\Project\Form;

use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Danger-zone delete project form (type-to-confirm + CSRF).
 */
final class ProjectDeleteType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $projectId = (int) $options['project_id'];

        $this->withBuilder($builder, function () use ($projectId): void {
            $this->addTextField('confirmation', [
                'required' => true,
                'label' => false,
                'attr' => [
                    'id' => 'project-delete-confirm-'.$projectId,
                    'class' => 'input w-full',
                    'autocomplete' => 'off',
                    'data-confirm-dialog-target' => 'confirmInput',
                ],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'project_delete',
            'project_id' => 0,
        ]);
        $resolver->setAllowedTypes('project_id', 'int');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return '';
    }
}
