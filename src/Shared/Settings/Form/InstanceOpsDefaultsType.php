<?php

declare(strict_types=1);

namespace App\Shared\Settings\Form;

use App\Shared\Settings\Entity\InstanceSettings;
use Nowo\PasswordToggleBundle\Form\Type\PasswordType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * Instance operational defaults (governance, ingest/security, metrics, inbound, notifications).
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
            ->add('envelopeMaxBytes', IntegerType::class, [
                'label' => 'ops_defaults.envelope_max_bytes.label',
                'help' => 'ops_defaults.envelope_max_bytes.help',
                'constraints' => $positive,
            ])
            ->add('ingestRejectQueryAuth', CheckboxType::class, [
                'required' => false,
                'label' => 'ops_defaults.ingest_reject_query_auth.label',
                'help' => 'ops_defaults.ingest_reject_query_auth.help',
            ])
            ->add('metricsRequireToken', CheckboxType::class, [
                'required' => false,
                'label' => 'ops_defaults.metrics_require_token.label',
                'help' => 'ops_defaults.metrics_require_token.help',
            ])
            ->add('plainMetricsToken', PasswordType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'ops_defaults.metrics_token.label',
                'help' => 'ops_defaults.metrics_token.help',
                'attr' => [
                    'autocomplete' => 'new-password',
                ],
                'constraints' => [
                    new Length(max: 512),
                ],
            ])
            ->add('clearMetricsToken', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'ops_defaults.clear_metrics_token.label',
                'help' => 'ops_defaults.clear_metrics_token.help',
            ])
            ->add('inboundEmailEnabled', CheckboxType::class, [
                'required' => false,
                'label' => 'ops_defaults.inbound_email_enabled.label',
                'help' => 'ops_defaults.inbound_email_enabled.help',
            ])
            ->add('inboundMailDomain', TextType::class, [
                'required' => false,
                'label' => 'ops_defaults.inbound_mail_domain.label',
                'help' => 'ops_defaults.inbound_mail_domain.help',
                'constraints' => [
                    new Length(max: 255),
                ],
            ])
            ->add('plainInboundWebhookSecret', PasswordType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'ops_defaults.inbound_webhook_secret.label',
                'help' => 'ops_defaults.inbound_webhook_secret.help',
                'attr' => [
                    'autocomplete' => 'new-password',
                ],
                'constraints' => [
                    new Length(max: 512),
                ],
            ])
            ->add('clearInboundWebhookSecret', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'ops_defaults.clear_inbound_webhook_secret.label',
                'help' => 'ops_defaults.clear_inbound_webhook_secret.help',
            ])
            ->add('allowPrivateUrls', CheckboxType::class, [
                'required' => false,
                'label' => 'ops_defaults.allow_private_urls.label',
                'help' => 'ops_defaults.allow_private_urls.help',
            ])
            ->add('allowAnonymousResolve', CheckboxType::class, [
                'required' => false,
                'label' => 'ops_defaults.allow_anonymous_resolve.label',
                'help' => 'ops_defaults.allow_anonymous_resolve.help',
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
