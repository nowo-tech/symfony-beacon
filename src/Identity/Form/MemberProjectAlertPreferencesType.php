<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Project\Entity\Project;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Current user's member-alert preferences for a single project (opt-out matrix).
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class MemberProjectAlertPreferencesType extends AbstractType
{
    public static function formNameForProject(Project $project): string
    {
        return self::formNameForUuid($project->getUuid());
    }

    public static function formNameForUuid(string $uuid): string
    {
        return 'project_alerts_'.str_replace('-', '', $uuid);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('enabled', CheckboxType::class, [
            'required' => false,
            'label' => 'preferences.member_alerts.project_enabled',
            'translation_domain' => 'messages',
        ]);
        $builder->add('resetOverrides', CheckboxType::class, [
            'required' => false,
            'label' => 'preferences.member_alerts.reset_overrides',
            'help' => 'preferences.member_alerts.reset_overrides_help',
            'translation_domain' => 'messages',
        ]);

        MemberAlertEventsFormBuilder::addEventsMatrix($builder, 'preferences.member_alerts.project_overrides');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'member_project_alert_preferences';
    }
}
