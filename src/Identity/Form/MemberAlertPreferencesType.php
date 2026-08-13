<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Account-level member alert preferences (master + event defaults + optional browser push).
 *
 * Per-project overrides use {@see MemberProjectAlertPreferencesType}.
 * Labels stay under {@code preferences.*} in the {@code form} catalogue (shared with Twig).
 */
final class MemberAlertPreferencesType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function () use ($options): void {
            $this->addCheckboxField('memberAlertsEnabled', [
                'required' => false,
                'placeholder' => false,
                'label' => 'preferences.member_alerts.master',
                'help' => 'preferences.member_alerts.master_help',
            ]);

            MemberAlertEventsFormBuilder::addEventsMatrix($this->boundBuilder());

            if ((bool) $options['push_available']) {
                $this->addCheckboxField('pushNotificationsEnabled', [
                    'required' => false,
                    'placeholder' => false,
                    'label' => 'preferences.push_notifications',
                    'help' => 'preferences.push_notifications_help',
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'push_available' => false,
        ]);
        $resolver->setAllowedTypes('push_available', 'bool');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'member_alert_preferences';
    }
}
