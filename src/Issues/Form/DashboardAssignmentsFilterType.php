<?php

declare(strict_types=1);

namespace App\Issues\Form;

use App\Issues\AssignmentScope;
use App\Shared\Form\AbstractGetFilterType;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Dashboard cross-project assignments filters (FormKit {@code filter}).
 */
final class DashboardAssignmentsFilterType extends AbstractGetFilterType
{
    public function __construct(
        FormOptionsMerger $formOptionsMerger,
        FormTypeMap $formTypeMap,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct($formOptionsMerger, $formTypeMap);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string> $projectChoices */
        $projectChoices = $options['project_choices'];
        /** @var array<int|string, string> $teammateChoices */
        $teammateChoices = $options['teammate_choices'];

        $scopeChoices = [];
        foreach (AssignmentScope::cases() as $scope) {
            $scopeChoices['dashboard_assignments_filter.scope.'.$scope->value] = $scope->value;
        }
        $priorityChoices = IssueListFilterFields::priorityChoicesWithAny('dashboard_assignments_filter');

        $assigneeChoices = [
            $this->translator->trans('dashboard_assignments_filter.assignee.any', [], 'form') => '',
        ];
        foreach ($teammateChoices as $id => $label) {
            $assigneeChoices[$label] = $id;
        }

        $this->withBuilder($builder, function () use (
            $projectChoices,
            $scopeChoices,
            $priorityChoices,
            $assigneeChoices,
        ): void {
            $this->addHiddenFilterField('sort');
            $this->addHiddenFilterField('dir');
            $this->addHiddenFilterField('page');

            $this->addFilterSelect('scope', [
                'choices' => $scopeChoices,
                'placeholder' => false,
                'attr' => [
                    'class' => 'input',
                    'aria-label' => $this->translator->trans(
                        'dashboard_assignments_filter.scope.aria',
                        [],
                        'form',
                    ),
                ],
                'row_attr' => ['class' => 'dashboard-filters__field'],
            ]);
            $this->addFilterSelect('project', [
                'choices' => $projectChoices,
                'choice_translation_domain' => false,
                'attr' => [
                    'class' => 'input',
                    'aria-label' => $this->translator->trans(
                        'dashboard_assignments_filter.project.aria',
                        [],
                        'form',
                    ),
                ],
                'row_attr' => ['class' => 'dashboard-filters__field'],
            ]);

            $this->addNamedField('q', 'search', [
                'attr' => [
                    'class' => 'input',
                    'aria-label' => $this->translator->trans(
                        'dashboard_assignments_filter.q.aria',
                        [],
                        'form',
                    ),
                ],
                'row_attr' => ['class' => 'dashboard-filters__field'],
            ]);

            $this->addFilterSelect('level', [
                'choices' => IssueListFilterFields::levelIdentityChoices(),
                'choice_translation_domain' => false,
                'attr' => [
                    'class' => 'input',
                    'aria-label' => $this->translator->trans(
                        'dashboard_assignments_filter.level.aria',
                        [],
                        'form',
                    ),
                ],
                'row_attr' => ['class' => 'dashboard-filters__field'],
            ]);
            $this->addFilterSelect('status', [
                'choices' => IssueListFilterFields::statusIdentityChoices(),
                'choice_translation_domain' => false,
                'placeholder' => false,
                'attr' => [
                    'class' => 'input',
                    'aria-label' => $this->translator->trans(
                        'dashboard_assignments_filter.status.aria',
                        [],
                        'form',
                    ),
                ],
                'row_attr' => ['class' => 'dashboard-filters__field'],
            ]);
            $this->addFilterSelect('priority', [
                'choices' => $priorityChoices,
                'placeholder' => false,
                'attr' => [
                    'class' => 'input',
                    'aria-label' => $this->translator->trans(
                        'dashboard_assignments_filter.priority.aria',
                        [],
                        'form',
                    ),
                ],
                'row_attr' => ['class' => 'dashboard-filters__field'],
            ]);
            $this->addFilterSelect('assignee', [
                'choices' => $assigneeChoices,
                'choice_translation_domain' => false,
                'placeholder' => false,
                'attr' => [
                    'class' => 'input',
                    'aria-label' => $this->translator->trans(
                        'dashboard_assignments_filter.assignee.aria',
                        [],
                        'form',
                    ),
                ],
                'row_attr' => ['class' => 'dashboard-filters__field'],
            ]);
            $this->addDashboardPerPage($this->translator);
        });
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

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'dashboard_assignments_filter';
    }
}
