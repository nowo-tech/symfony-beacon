<?php

declare(strict_types=1);

namespace App\Identity\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Account-level member alert preferences (master + event defaults + optional browser push).
 *
 * Per-project overrides use {@see MemberProjectAlertPreferencesType}.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class MemberAlertPreferencesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('memberAlertsEnabled', CheckboxType::class, [
            'required' => false,
            'label' => 'preferences.member_alerts.master',
            'help' => 'preferences.member_alerts.master_help',
            'translation_domain' => 'messages',
        ]);

        MemberAlertEventsFormBuilder::addEventsMatrix($builder);

        if ((bool) $options['push_available']) {
            $builder->add('pushNotificationsEnabled', CheckboxType::class, [
                'required' => false,
                'label' => 'preferences.push_notifications',
                'help' => 'preferences.push_notifications_help',
                'translation_domain' => 'messages',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
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
