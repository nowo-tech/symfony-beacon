<?php

declare(strict_types=1);

namespace App\Issues\Form;

use App\Shared\Form\AbstractGetFilterType;
use App\Shared\Form\DashboardProjectFilterFields;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * GET filters for the per-project issue index (FormKit {@code filter} profile).
 *
 * Placeholders live in {@code translations/form.*.yaml} under {@code issue_index_filter.*}.
 * Choice empty-option copy is applied after FormKit merge (ChoiceType root {@code placeholder}).
 */
final class IssueIndexFilterType extends AbstractGetFilterType
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
        /** @var list<string> $levelChoices */
        $levelChoices = $options['level_choices'];
        /** @var array<int|string, string> $memberChoices */
        $memberChoices = $options['member_choices'];

        $priorityChoices = IssueListFilterFields::priorityChoicesWithAny('issue_index_filter');
        $statusChoices = IssueListFilterFields::statusChoicesWithAny('issue_index_filter');
        // Member display names stay literal; static options are translated once here.
        $assigneeChoices = [
            $this->translator->trans('issue_index_filter.assignee.any', [], 'form') => '',
            $this->translator->trans('issue_index_filter.assignee.unassigned', [], 'form') => 'unassigned',
        ];
        foreach ($memberChoices as $id => $label) {
            $assigneeChoices[$label] = $id;
        }
        $perPageChoices = [];
        foreach (DashboardProjectFilterFields::PER_PAGE_SIZES as $size) {
            $perPageChoices[$this->translator->trans(
                'issue_index_filter.per_page.option',
                ['%count%' => (string) $size],
                'form',
            )] = $size;
        }

        $this->withBuilder($builder, function () use (
            $levelChoices,
            $statusChoices,
            $priorityChoices,
            $assigneeChoices,
            $perPageChoices,
        ): void {
            $this->addHiddenFilterField('sort');
            $this->addHiddenFilterField('dir');
            $this->addHiddenFilterField('page');

            $this->addNamedField('q', 'search', [
                'attr' => ['class' => 'input issue-filters__search'],
                'row_attr' => ['class' => 'issue-filters__field'],
            ]);

            $this->addFilterSelect('level', [
                'choices' => array_combine($levelChoices, $levelChoices),
                'choice_translation_domain' => false,
                'row_attr' => ['class' => 'issue-filters__field'],
            ]);
            $this->addFilterSelect('status', [
                'choices' => $statusChoices,
                'placeholder' => false,
                'row_attr' => ['class' => 'issue-filters__field'],
            ]);
            $this->addFilterSelect('priority', [
                'choices' => $priorityChoices,
                'placeholder' => false,
                'row_attr' => ['class' => 'issue-filters__field'],
            ]);
            $this->addFilterSelect('assignee', [
                'choices' => $assigneeChoices,
                'choice_translation_domain' => false,
                'placeholder' => false,
                'row_attr' => ['class' => 'issue-filters__field'],
            ]);

            $this->addTextField('environment', [
                'row_attr' => ['class' => 'issue-filters__field'],
            ]);
            $this->addTextField('compare', [
                'row_attr' => ['class' => 'issue-filters__field'],
            ]);
            $this->addTextField('release', [
                'row_attr' => ['class' => 'issue-filters__field'],
            ]);
            $this->addTextField('tag', [
                'row_attr' => ['class' => 'issue-filters__field'],
            ]);
            $this->addTextField('url', [
                'row_attr' => ['class' => 'issue-filters__field'],
            ]);
            $this->addTextField('user', [
                'row_attr' => ['class' => 'issue-filters__field'],
            ]);

            $this->addFilterSelect('per_page', [
                'required' => true,
                'choices' => $perPageChoices,
                'choice_translation_domain' => false,
                'placeholder' => false,
                'attr' => [
                    'aria-label' => $this->translator->trans('issue_index_filter.per_page.aria', [], 'form'),
                ],
                'row_attr' => ['class' => 'issue-filters__field'],
            ]);
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'level_choices' => [],
            'member_choices' => [],
        ]);
        $resolver->setAllowedTypes('level_choices', 'array');
        $resolver->setAllowedTypes('member_choices', 'array');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'issue_index_filter';
    }
}
