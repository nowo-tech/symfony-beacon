<?php

declare(strict_types=1);

namespace App\Ops\Form;

use App\Shared\Form\AbstractGetFilterType;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Admin operations overview filters (FormKit {@code filter}).
 */
final class AdminOpsOverviewFilterType extends AbstractGetFilterType
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

        $this->withBuilder($builder, function () use ($projectChoices): void {
            $this->addFilterSelect('project', [
                'choices' => $projectChoices,
                'choice_translation_domain' => false,
                'attr' => [
                    'class' => 'min-w-[12rem] rounded border border-[var(--color-ink)]/20 bg-transparent px-2 py-1.5',
                    'aria-label' => $this->translator->trans('admin.ops.filter_project', [], 'form'),
                ],
                'row_attr' => ['class' => 'dashboard-filters__field min-w-[12rem]'],
            ]);
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'project_choices' => [],
        ]);
        $resolver->setAllowedTypes('project_choices', 'array');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'admin_ops_overview_filter';
    }
}
