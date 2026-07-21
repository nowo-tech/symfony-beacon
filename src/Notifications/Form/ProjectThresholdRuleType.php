<?php

declare(strict_types=1);

namespace App\Notifications\Form;

use App\Notifications\Entity\ProjectThresholdRule;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Create/edit a project error-volume threshold rule.
 *
 * @extends AbstractType<ProjectThresholdRule>
 */
final class ProjectThresholdRuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'thresholds.form.label',
                'required' => false,
                'help' => 'thresholds.form.label_help',
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'thresholds.form.enabled',
                'required' => false,
            ])
            ->add('errorCount', IntegerType::class, [
                'label' => 'thresholds.form.error_count',
                'help' => 'thresholds.form.error_count_help',
                'attr' => ['min' => 1, 'max' => 1000000],
            ])
            ->add('windowMinutes', IntegerType::class, [
                'label' => 'thresholds.form.window_minutes',
                'help' => 'thresholds.form.window_minutes_help',
                'attr' => ['min' => 1, 'max' => 1440],
            ])
            ->add('cooldownMinutes', IntegerType::class, [
                'label' => 'thresholds.form.cooldown_minutes',
                'help' => 'thresholds.form.cooldown_minutes_help',
                'attr' => ['min' => 1, 'max' => 10080],
            ])
            ->add('environment', TextType::class, [
                'label' => 'thresholds.form.environment',
                'required' => false,
                'help' => 'thresholds.form.environment_help',
            ])
            ->add('releaseVersion', TextType::class, [
                'label' => 'thresholds.form.release',
                'required' => false,
                'help' => 'thresholds.form.release_help',
            ]);
    }

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
