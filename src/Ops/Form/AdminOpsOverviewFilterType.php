<?php

declare(strict_types=1);

namespace App\Ops\Form;

use App\Shared\Form\AbstractGetFilterType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Admin operations overview filters.
 */
final class AdminOpsOverviewFilterType extends AbstractGetFilterType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string> $projectChoices */
        $projectChoices = $options['project_choices'];

        $builder->add('project', ChoiceType::class, [
            'label' => false,
            'required' => false,
            'choices' => $projectChoices,
            'choice_translation_domain' => false,
            'placeholder' => $this->translator->trans('admin.ops.filter_all'),
            'attr' => [
                'class' => 'min-w-[12rem] rounded border border-[var(--color-ink)]/20 bg-transparent px-2 py-1.5',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'project_choices' => [],
        ]);
        $resolver->setAllowedTypes('project_choices', 'array');
    }
}
