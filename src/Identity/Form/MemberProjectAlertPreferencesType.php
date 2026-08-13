<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Project\Entity\Project;
use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Current user's member-alert preferences for a single project (opt-out matrix).
 *
 * Labels stay under {@code preferences.*} in the {@code form} catalogue (shared with Twig).
 */
final class MemberProjectAlertPreferencesType extends FormKitAbstractType
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
        $this->withBuilder($builder, function (): void {
            $this->addCheckboxField('enabled', [
                'required' => false,
                'placeholder' => false,
                'label' => 'preferences.member_alerts.project_enabled',
                'help' => false,
            ]);
            $this->addCheckboxField('resetOverrides', [
                'required' => false,
                'placeholder' => false,
                'label' => 'preferences.member_alerts.reset_overrides',
                'help' => 'preferences.member_alerts.reset_overrides_help',
            ]);

            MemberAlertEventsFormBuilder::addEventsMatrix(
                $this->boundBuilder(),
                'preferences.member_alerts.project_overrides',
            );
        });
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'member_project_alert_preferences';
    }
}
