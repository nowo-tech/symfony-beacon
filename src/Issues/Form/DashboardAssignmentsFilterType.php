<?php

declare(strict_types=1);

namespace App\Issues\Form;

use App\Issues\AssignmentScope;
use App\Shared\Form\AbstractGetFilterType;
use Override;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Dashboard cross-project assignments filters.
 */
final class DashboardAssignmentsFilterType extends AbstractGetFilterType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string> $projectChoices */
        $projectChoices = $options['project_choices'];
        /** @var array<string, string> $teammateChoices */
        $teammateChoices = $options['teammate_choices'];
        $scopeChoices = [];
        foreach (AssignmentScope::cases() as $scope) {
            $scopeChoices[$this->translator->trans('assignments.scope.'.$scope->value)] = $scope->value;
        }
        $priorityChoices = [
            $this->translator->trans('issues.filter.any_priority') => '',
        ];
        foreach (['low', 'medium', 'high', 'critical'] as $priority) {
            $priorityChoices[$this->translator->trans('issues.priority.'.$priority)] = $priority;
        }
        $assigneeChoices = [
            $this->translator->trans('issues.filter.any_assignee') => '',
        ];
        foreach ($teammateChoices as $id => $label) {
            $assigneeChoices[$label] = $id;
        }
        $perPageChoices = [];
        foreach ([10, 25, 50, 100] as $size) {
            $perPageChoices[$this->translator->trans('issues.filter.per_page_option', ['%count%' => (string) $size])] = $size;
        }

        $builder
            ->add('sort', HiddenType::class)
            ->add('dir', HiddenType::class)
            ->add('page', HiddenType::class)
            ->add('scope', ChoiceType::class, [
                'label' => false,
                'required' => true,
                'choices' => $scopeChoices,
                'choice_translation_domain' => false,
                'placeholder' => false,
                'attr' => [
                    'class' => 'input',
                    'aria-label' => 'assignments.filter.scope',
                ],
            ])
            ->add('project', ChoiceType::class, [
                'label' => false,
                'required' => false,
                'choices' => $projectChoices,
                'choice_translation_domain' => false,
                'placeholder' => $this->translator->trans('assignments.filter.any_project'),
                'attr' => [
                    'class' => 'input',
                    'aria-label' => 'assignments.filter.project',
                ],
            ])
            ->add('q', SearchType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'input',
                ],
            ])
            ->add('level', ChoiceType::class, [
                'label' => false,
                'required' => false,
                'choices' => [
                    'fatal' => 'fatal',
                    'error' => 'error',
                    'warning' => 'warning',
                    'info' => 'info',
                    'debug' => 'debug',
                ],
                'choice_translation_domain' => false,
                'placeholder' => $this->translator->trans('issues.filter.any_level'),
                'attr' => [
                    'class' => 'input',
                    'aria-label' => 'assignments.filter.level',
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => false,
                'required' => true,
                'choices' => ['unresolved', 'resolved', 'ignored'],
                'choice_translation_domain' => false,
                'placeholder' => false,
                'attr' => [
                    'class' => 'input',
                    'aria-label' => 'assignments.filter.status',
                ],
            ])
            ->add('priority', ChoiceType::class, [
                'label' => false,
                'required' => false,
                'choices' => $priorityChoices,
                'choice_translation_domain' => false,
                'placeholder' => false,
                'attr' => [
                    'class' => 'input',
                    'aria-label' => 'assignments.filter.priority',
                ],
            ])
            ->add('assignee', ChoiceType::class, [
                'label' => false,
                'required' => false,
                'choices' => $assigneeChoices,
                'choice_translation_domain' => false,
                'placeholder' => false,
                'attr' => [
                    'class' => 'input',
                    'aria-label' => 'assignments.filter.assignee',
                ],
            ])
            ->add('per_page', ChoiceType::class, [
                'label' => false,
                'required' => true,
                'choices' => $perPageChoices,
                'choice_translation_domain' => false,
                'placeholder' => false,
                'attr' => [
                    'class' => 'input',
                    'aria-label' => 'issues.filter.per_page',
                ],
            ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'project_choices' => [],
            'teammate_choices' => [],
        ]);
        $resolver->setAllowedTypes('project_choices', 'array');
        $resolver->setAllowedTypes('teammate_choices', 'array');
    }
}
