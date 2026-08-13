<?php

declare(strict_types=1);

namespace App\Issues\Form;

use App\Shared\Form\AbstractGetFilterType;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Dashboard "new in release" filters (FormKit {@code filter}).
 */
final class DashboardNewInReleaseFilterType extends AbstractGetFilterType
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
        /** @var array<string, string> $releaseChoices */
        $releaseChoices = $options['release_choices'];

        $this->withBuilder($builder, function () use ($projectChoices, $releaseChoices): void {
            $this->addDashboardPageAndProject(
                $this->translator,
                $projectChoices,
                'dashboard_new_in_release_filter.project.aria',
            );
            $this->addFilterSelect('release', [
                'choices' => $releaseChoices,
                'choice_translation_domain' => false,
                'attr' => [
                    'class' => 'input',
                    'aria-label' => $this->translator->trans(
                        'dashboard_new_in_release_filter.release.aria',
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
            'release_choices' => [],
        ]);
        $resolver->setAllowedTypes('project_choices', 'array');
        $resolver->setAllowedTypes('release_choices', 'array');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'dashboard_new_in_release_filter';
    }
}
