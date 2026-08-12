<?php

declare(strict_types=1);

namespace App\Project\Form;

use Override;
use App\Shared\Form\AbstractGetFilterType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Release focus and compare filters on the project releases page.
 */
final class ProjectReleaseFocusType extends AbstractGetFilterType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string> $releaseChoices */
        $releaseChoices = $options['release_choices'];
        /** @var array<string, string> $compareChoices */
        $compareChoices = $options['compare_choices'];

        $builder
            ->add('release', ChoiceType::class, [
                'label' => false,
                'required' => true,
                'choices' => $releaseChoices,
                'choice_translation_domain' => false,
                'placeholder' => false,
                'attr' => [
                    'class' => 'input w-full',
                ],
            ])
            ->add('compare', ChoiceType::class, [
                'label' => false,
                'required' => false,
                'choices' => $compareChoices,
                'choice_translation_domain' => false,
                'placeholder' => $this->translator->trans('releases.compare.none'),
                'attr' => [
                    'class' => 'input w-full',
                ],
            ]);
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
}
