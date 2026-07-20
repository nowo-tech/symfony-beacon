<?php

declare(strict_types=1);

namespace App\Issues\Form;

use App\Issues\Entity\Issue;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Assign an issue to a project member (or clear assignment).
 *
 * @extends AbstractType<Issue>
 */
final class IssueAssigneeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('assignee', ProjectMemberAutocompleteField::class, [
            'label' => false,
            'extra_options' => [
                'project_id' => $options['project_id'],
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Issue::class,
        ]);
        $resolver->setRequired(['project_id']);
        $resolver->setAllowedTypes('project_id', 'int');
    }
}
