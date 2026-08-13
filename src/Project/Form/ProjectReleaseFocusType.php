<?php

declare(strict_types=1);

namespace App\Project\Form;

use App\Shared\Form\AbstractGetFilterType;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Release focus and compare filters on the project releases page (FormKit {@code filter}).
 *
 * Release version labels stay literal ({@code choice_translation_domain: false}).
 * Compare empty option: {@code translations/form.*.yaml} → {@code project_release_focus.compare.placeholder}.
 */
final class ProjectReleaseFocusType extends AbstractGetFilterType
{
    public function __construct(
        FormOptionsMerger $formOptionsMerger,
        FormTypeMap $formTypeMap,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct($formOptionsMerger, $formTypeMap);
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string> $releaseChoices */
        $releaseChoices = $options['release_choices'];
        /** @var array<string, string> $compareChoices */
        $compareChoices = $options['compare_choices'];

        $this->withBuilder($builder, function () use ($releaseChoices, $compareChoices): void {
            $this->addFilterSelect('release', [
                'choices' => $releaseChoices,
                'choice_translation_domain' => false,
                'attr' => [
                    'id' => 'release-select',
                    'class' => 'input w-full',
                    'aria-label' => $this->translator->trans('releases.focus.label', [], 'form'),
                ],
            ]);
            $this->addFilterSelect('compare', [
                'choices' => $compareChoices,
                'choice_translation_domain' => false,
                'attr' => [
                    'id' => 'compare-select',
                    'class' => 'input w-full',
                    'aria-label' => $this->translator->trans('releases.compare.label', [], 'form'),
                ],
            ]);
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'release_choices' => [],
            'compare_choices' => [],
        ]);
        $resolver->setAllowedTypes('release_choices', 'array');
        $resolver->setAllowedTypes('compare_choices', 'array');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'project_release_focus';
    }
}
