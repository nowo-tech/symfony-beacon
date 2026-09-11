<?php

declare(strict_types=1);

namespace App\Notifications\Form;

use App\Notifications\Entity\ProjectThresholdRule;
use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Create/edit a project error-volume threshold rule (FormKit {@code beacon}).
 *
 * Catalogue: {@code translations/form.*.yaml} → {@code project_threshold_rule.*}.
 */
final class ProjectThresholdRuleType extends FormKitAbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addTextField('label', [
                'required' => false,
            ]);
            $this->addCheckboxField('enabled', [
                'required' => false,
                'placeholder' => false,
            ]);
            $this->addIntegerField('errorCount', [
                'attr' => ['min' => 1, 'max' => 1000000],
            ]);
            $this->addIntegerField('windowMinutes', [
                'attr' => ['min' => 1, 'max' => 1440],
            ]);
            $this->addIntegerField('cooldownMinutes', [
                'attr' => ['min' => 1, 'max' => 10080],
            ]);
            $this->addTextField('environment', [
                'required' => false,
            ]);
            $this->addTextField('releaseVersion', [
                'required' => false,
            ]);
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProjectThresholdRule::class,
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'project_threshold_rule';
    }
}
