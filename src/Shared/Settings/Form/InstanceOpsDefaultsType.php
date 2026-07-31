<?php

declare(strict_types=1);

namespace App\Shared\Settings\Form;

use App\Shared\Settings\Entity\InstanceSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * Instance operational defaults (governance inherit + notification limits).
 *
 * @extends AbstractType<InstanceSettings>
 */
final class InstanceOpsDefaultsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $nonNegative = [
            new NotNull(),
            new GreaterThanOrEqual(0),
            new LessThanOrEqual(2_147_483_647),
        ];
        $positive = [
            new NotNull(),
            new GreaterThanOrEqual(1),
            new LessThanOrEqual(2_147_483_647),
        ];

        $builder
            ->add('retentionDays', IntegerType::class, [
                'label' => 'ops_defaults.retention_days.label',
                'help' => 'ops_defaults.retention_days.help',
                'constraints' => $nonNegative,
            ])
            ->add('retentionMaxEvents', IntegerType::class, [
                'label' => 'ops_defaults.retention_max_events.label',
                'help' => 'ops_defaults.retention_max_events.help',
                'constraints' => $nonNegative,
            ])
            ->add('ingestRateLimit', IntegerType::class, [
                'label' => 'ops_defaults.ingest_rate_limit.label',
                'help' => 'ops_defaults.ingest_rate_limit.help',
                'constraints' => $nonNegative,
            ])
            ->add('eventQuotaDaily', IntegerType::class, [
                'label' => 'ops_defaults.event_quota_daily.label',
                'help' => 'ops_defaults.event_quota_daily.help',
                'constraints' => $nonNegative,
            ])
            ->add('eventQuotaMonthly', IntegerType::class, [
                'label' => 'ops_defaults.event_quota_monthly.label',
                'help' => 'ops_defaults.event_quota_monthly.help',
                'constraints' => $nonNegative,
            ])
            ->add('notificationDeliveryHistoryLimit', IntegerType::class, [
                'label' => 'ops_defaults.delivery_history_limit.label',
                'help' => 'ops_defaults.delivery_history_limit.help',
                'constraints' => $positive,
            ])
            ->add('notificationCircuitBreakerThreshold', IntegerType::class, [
                'label' => 'ops_defaults.circuit_breaker_threshold.label',
                'help' => 'ops_defaults.circuit_breaker_threshold.help',
                'constraints' => $positive,
            ])
            ->add('notificationCircuitBreakerCooldownMinutes', IntegerType::class, [
                'label' => 'ops_defaults.circuit_breaker_cooldown.label',
                'help' => 'ops_defaults.circuit_breaker_cooldown.help',
                'constraints' => $nonNegative,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InstanceSettings::class,
            'translation_domain' => 'messages',
        ]);
    }
}
