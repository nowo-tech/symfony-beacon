<?php

declare(strict_types=1);

namespace App\Project\Form;

use App\Shared\Form\AbstractGetFilterType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Issue-list deep-link form for release environment comparison (FormKit {@code filter}: no labels).
 *
 * Placeholders / help: {@code translations/form.*.yaml} → {@code project_release_environment_compare.*}.
 * Visible captions are Twig chrome ({@code releases.environment_compare.*}).
 */
final class ProjectReleaseEnvironmentCompareType extends AbstractGetFilterType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addHiddenFilterField('status');
            $this->addHiddenFilterField('release');
            $this->addTextField('environment', [
                'attr' => [
                    'id' => 'env-a',
                    'class' => 'input w-full',
                ],
            ]);
            $this->addTextField('compare', [
                'attr' => [
                    'id' => 'env-b',
                    'class' => 'input w-full',
                ],
            ]);
        });
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'project_release_environment_compare';
    }
}
