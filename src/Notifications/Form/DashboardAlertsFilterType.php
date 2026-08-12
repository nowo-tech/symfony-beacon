<?php

declare(strict_types=1);

namespace App\Notifications\Form;

use App\Shared\Form\AbstractGetFilterType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Dashboard alerts filters.
 */
final class DashboardAlertsFilterType extends AbstractGetFilterType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string> $projectChoices */
        $projectChoices = $options['project_choices'];
        $perPageChoices = [];
        foreach ([10, 25, 50, 100] as $size) {
            $perPageChoices[$this->translator->trans('issues.filter.per_page_option', ['%count%' => (string) $size])] = $size;
        }

        $builder
            ->add('page', HiddenType::class)
            ->add('project', ChoiceType::class, [
                'label' => false,
                'required' => false,
                'choices' => $projectChoices,
                'choice_translation_domain' => false,
                'placeholder' => $this->translator->trans('alerts.filter.any_project'),
                'attr' => [
                    'class' => 'input',
                    'aria-label' => 'alerts.filter.project',
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

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'project_choices' => [],
        ]);
        $resolver->setAllowedTypes('project_choices', 'array');
    }
}
