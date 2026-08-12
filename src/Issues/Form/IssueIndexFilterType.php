<?php

declare(strict_types=1);

namespace App\Issues\Form;

use App\Shared\Form\AbstractGetFilterType;
use Override;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * GET filters for the per-project issue index.
 */
final class IssueIndexFilterType extends AbstractGetFilterType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $levelChoices */
        $levelChoices = $options['level_choices'];
        /** @var array<string, string> $memberChoices */
        $memberChoices = $options['member_choices'];
        $statusChoices = [
            'unresolved' => 'unresolved',
            'resolved' => 'resolved',
            'ignored' => 'ignored',
            $this->translator->trans('issues.filter.any_status') => '',
        ];
        $priorityChoices = [
            $this->translator->trans('issues.filter.any_priority') => '',
        ];
        foreach (['low', 'medium', 'high', 'critical'] as $priority) {
            $priorityChoices[$this->translator->trans('issues.priority.'.$priority)] = $priority;
        }
        $assigneeChoices = [
            $this->translator->trans('issues.filter.any_assignee') => '',
            $this->translator->trans('issues.assignee_unassigned') => 'unassigned',
        ];
        foreach ($memberChoices as $id => $label) {
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
            ->add('q', SearchType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'input issue-filters__search',
                ],
            ])
            ->add('level', ChoiceType::class, [
                'label' => false,
                'required' => false,
                'choices' => array_combine($levelChoices, $levelChoices),
                'choice_translation_domain' => false,
                'placeholder' => $this->translator->trans('issues.filter.any_level'),
                'attr' => [
                    'class' => 'input',
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => false,
                'required' => false,
                'choices' => $statusChoices,
                'choice_translation_domain' => false,
                'placeholder' => false,
                'attr' => [
                    'class' => 'input',
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
                ],
            ])
            ->add('environment', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'input',
                ],
            ])
            ->add('compare', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'input',
                ],
            ])
            ->add('release', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'input',
                ],
            ])
            ->add('tag', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'input',
                ],
            ])
            ->add('url', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'input',
                ],
            ])
            ->add('user', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'input',
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
            'level_choices' => [],
            'member_choices' => [],
        ]);
        $resolver->setAllowedTypes('level_choices', 'array');
        $resolver->setAllowedTypes('member_choices', 'array');
    }
}
