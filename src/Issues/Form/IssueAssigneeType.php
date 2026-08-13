<?php

declare(strict_types=1);

namespace App\Issues\Form;

use App\Issues\Entity\Issue;
use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Assign an issue to a project member (FormKit {@code beacon}).
 *
 * Catalogue: {@code translations/form.*.yaml} → {@code issue_assignee.*}.
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
                    'help' => false,
                    'placeholder' => false,
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
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => Issue::class,
        ]);
        $resolver->setRequired(['project_id']);
        $resolver->setAllowedTypes('project_id', 'int');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'issue_assignee';
    }
}
