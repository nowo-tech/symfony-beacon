<?php

declare(strict_types=1);

namespace App\Issues\Form;

use App\Issues\Entity\Issue;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Assign an issue to a project member (or clear assignment) — FormKit.
 */
final class IssueAssigneeType extends FormKitAbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function () use ($options): void {
            $this->boundBuilder()->add(
                'assignee',
                ProjectMemberAutocompleteField::class,
                $this->mergeFieldOptions('assignee', 'choice', [
                    'label' => false,
                    'extra_options' => [
                        'project_id' => $options['project_id'],
                    ],
                ]),
            );
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Issue::class,
        ]);
        $resolver->setRequired(['project_id']);
        $resolver->setAllowedTypes('project_id', 'int');
    }
}
