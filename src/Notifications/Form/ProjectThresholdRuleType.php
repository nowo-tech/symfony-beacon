<?php

declare(strict_types=1);

namespace App\Notifications\Form;

use App\Notifications\Entity\ProjectThresholdRule;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Create/edit a project error-volume threshold rule (FormKit).
 */
final class ProjectThresholdRuleType extends FormKitAbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addTextField('label', [
                'label' => 'thresholds.form.label',
                'required' => false,
                'help' => 'thresholds.form.label_help',
            ]);
            $this->addCheckboxField('enabled', [
                'label' => 'thresholds.form.enabled',
                'required' => false,
            ]);
            $this->addIntegerField('errorCount', [
                'label' => 'thresholds.form.error_count',
                'help' => 'thresholds.form.error_count_help',
                'attr' => ['min' => 1, 'max' => 1000000],
            ]);
            $this->addIntegerField('windowMinutes', [
                'label' => 'thresholds.form.window_minutes',
                'help' => 'thresholds.form.window_minutes_help',
                'attr' => ['min' => 1, 'max' => 1440],
            ]);
            $this->addIntegerField('cooldownMinutes', [
                'label' => 'thresholds.form.cooldown_minutes',
                'help' => 'thresholds.form.cooldown_minutes_help',
                'attr' => ['min' => 1, 'max' => 10080],
            ]);
            $this->addTextField('environment', [
                'label' => 'thresholds.form.environment',
                'required' => false,
                'help' => 'thresholds.form.environment_help',
            ]);
            $this->addTextField('releaseVersion', [
                'label' => 'thresholds.form.release',
                'required' => false,
                'help' => 'thresholds.form.release_help',
            ]);
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProjectThresholdRule::class,
            'translation_domain' => 'messages',
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'project_threshold_rule';
    }
}
