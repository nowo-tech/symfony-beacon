<?php

declare(strict_types=1);

namespace App\Shared\Settings\Form;

use App\Shared\Form\FormKitAbstractType;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\OpsDefaultsSection;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\PasswordToggleBundle\Form\Type\PasswordType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Instance operational defaults — one field group per {@see OpsDefaultsSection} tab.
 */
final class InstanceOpsDefaultsType extends FormKitAbstractType
{
    public function __construct(
        FormOptionsMerger $formOptionsMerger,
        FormTypeMap $formTypeMap,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct($formOptionsMerger, $formTypeMap);
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var OpsDefaultsSection $section */
        $section = $options['section'];
        $settings = $builder->getData();
        $allowPrivateUrlsWasEnabled = $settings instanceof InstanceSettings && $settings->isAllowPrivateUrls();
        $allowAnonymousResolveWasEnabled = $settings instanceof InstanceSettings && $settings->isAllowAnonymousResolve();
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

        $this->withBuilder($builder, function () use ($section, $nonNegative, $positive): void {
            match ($section) {
                OpsDefaultsSection::Governance => $this->addGovernanceFields($nonNegative),
                OpsDefaultsSection::Ingest => $this->addIngestFields($positive),
                OpsDefaultsSection::Metrics => $this->addMetricsFields(),
                OpsDefaultsSection::Inbound => $this->addInboundFields(),
                OpsDefaultsSection::Notifications => $this->addNotificationFields($nonNegative, $positive),
            };
        });

        if (OpsDefaultsSection::Notifications === $section) {
            $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use ($allowPrivateUrlsWasEnabled, $allowAnonymousResolveWasEnabled): void {
                $form = $event->getForm();
                $data = $event->getData();
                if (!$data instanceof InstanceSettings) {
                    return;
                }

                if (!$allowPrivateUrlsWasEnabled && $data->isAllowPrivateUrls()) {
                    $confirmation = trim((string) $form->get('confirmAllowPrivateUrls')->getData());
                    if ('ALLOW_PRIVATE_URLS' !== $confirmation) {
                        $form->get('confirmAllowPrivateUrls')->addError(new FormError(
                            $this->translator->trans('ops_defaults.confirm_allow_private_urls.invalid', [], 'form'),
                        ));
                    }
                }

                if (!$allowAnonymousResolveWasEnabled && $data->isAllowAnonymousResolve()) {
                    $confirmation = trim((string) $form->get('confirmAllowAnonymousResolve')->getData());
                    if ('ALLOW_ANONYMOUS_RESOLVE' !== $confirmation) {
                        $form->get('confirmAllowAnonymousResolve')->addError(new FormError(
                            $this->translator->trans('ops_defaults.confirm_allow_anonymous_resolve.invalid', [], 'form'),
                        ));
                    }
                }
            });
        }
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InstanceSettings::class,
            'section' => OpsDefaultsSection::Governance,
        ]);
        $resolver->setAllowedTypes('section', OpsDefaultsSection::class);
    }

    /**
     * @param list<Constraint> $nonNegative
     */
    private function addGovernanceFields(array $nonNegative): void
    {
        $this->addIntegerField('retentionDays', [
            'placeholder' => false,
            'label' => 'ops_defaults.retention_days.label',
            'help' => 'ops_defaults.retention_days.help',
            'constraints' => $nonNegative,
        ]);
        $this->addIntegerField('retentionMaxEvents', [
            'placeholder' => false,
            'label' => 'ops_defaults.retention_max_events.label',
            'help' => 'ops_defaults.retention_max_events.help',
            'constraints' => $nonNegative,
        ]);
        $this->addIntegerField('ingestRateLimit', [
            'placeholder' => false,
            'label' => 'ops_defaults.ingest_rate_limit.label',
            'help' => 'ops_defaults.ingest_rate_limit.help',
            'constraints' => $nonNegative,
        ]);
        $this->addIntegerField('eventQuotaDaily', [
            'placeholder' => false,
            'label' => 'ops_defaults.event_quota_daily.label',
            'help' => 'ops_defaults.event_quota_daily.help',
            'constraints' => $nonNegative,
        ]);
        $this->addIntegerField('eventQuotaMonthly', [
            'placeholder' => false,
            'label' => 'ops_defaults.event_quota_monthly.label',
            'help' => 'ops_defaults.event_quota_monthly.help',
            'constraints' => $nonNegative,
        ]);
    }

    /**
     * @param list<Constraint> $positive
     */
    private function addIngestFields(array $positive): void
    {
        $this->addIntegerField('envelopeMaxBytes', [
            'placeholder' => false,
            'label' => 'ops_defaults.envelope_max_bytes.label',
            'help' => 'ops_defaults.envelope_max_bytes.help',
            'constraints' => $positive,
        ]);
    }

    private function addMetricsFields(): void
    {
        $this->addCheckboxField('metricsRequireToken', [
            'placeholder' => false,
            'required' => false,
            'label' => 'ops_defaults.metrics_require_token.label',
            'help' => 'ops_defaults.metrics_require_token.help',
        ]);
        $this->addTogglePassword('plainMetricsToken', [
            'placeholder' => false,
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
        ]);
        $this->addCheckboxField('clearMetricsToken', [
            'placeholder' => false,
            'mapped' => false,
            'required' => false,
            'label' => 'ops_defaults.clear_metrics_token.label',
            'help' => 'ops_defaults.clear_metrics_token.help',
        ]);
    }

    private function addInboundFields(): void
    {
        $this->addCheckboxField('inboundEmailEnabled', [
            'placeholder' => false,
            'required' => false,
            'label' => 'ops_defaults.inbound_email_enabled.label',
            'help' => 'ops_defaults.inbound_email_enabled.help',
        ]);
        $this->addTextField('inboundMailDomain', [
            'placeholder' => false,
            'required' => false,
            'label' => 'ops_defaults.inbound_mail_domain.label',
            'help' => 'ops_defaults.inbound_mail_domain.help',
            'constraints' => [
                new Length(max: 255),
            ],
        ]);
        $this->addTogglePassword('plainInboundWebhookSecret', [
            'placeholder' => false,
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
        ]);
        $this->addCheckboxField('clearInboundWebhookSecret', [
            'placeholder' => false,
            'mapped' => false,
            'required' => false,
            'label' => 'ops_defaults.clear_inbound_webhook_secret.label',
            'help' => 'ops_defaults.clear_inbound_webhook_secret.help',
        ]);
    }

    /**
     * @param list<Constraint> $nonNegative
     * @param list<Constraint> $positive
     */
    private function addNotificationFields(array $nonNegative, array $positive): void
    {
        $this->addCheckboxField('allowPrivateUrls', [
            'placeholder' => false,
            'required' => false,
            'label' => 'ops_defaults.allow_private_urls.label',
            'help' => 'ops_defaults.allow_private_urls.help',
        ]);
        $this->addTextField('confirmAllowPrivateUrls', [
            'mapped' => false,
            'required' => false,
            'label' => 'ops_defaults.confirm_allow_private_urls.label',
            'help' => 'ops_defaults.confirm_allow_private_urls.help',
        ]);
        $this->addCheckboxField('allowAnonymousResolve', [
            'placeholder' => false,
            'required' => false,
            'label' => 'ops_defaults.allow_anonymous_resolve.label',
            'help' => 'ops_defaults.allow_anonymous_resolve.help',
        ]);
        $this->addTextField('confirmAllowAnonymousResolve', [
            'mapped' => false,
            'required' => false,
            'label' => 'ops_defaults.confirm_allow_anonymous_resolve.label',
            'help' => 'ops_defaults.confirm_allow_anonymous_resolve.help',
        ]);
        $this->addIntegerField('notificationDeliveryHistoryLimit', [
            'placeholder' => false,
            'label' => 'ops_defaults.delivery_history_limit.label',
            'help' => 'ops_defaults.delivery_history_limit.help',
            'constraints' => $positive,
        ]);
        $this->addIntegerField('notificationCircuitBreakerThreshold', [
            'placeholder' => false,
            'label' => 'ops_defaults.circuit_breaker_threshold.label',
            'help' => 'ops_defaults.circuit_breaker_threshold.help',
            'constraints' => $positive,
        ]);
        $this->addIntegerField('notificationCircuitBreakerCooldownMinutes', [
            'placeholder' => false,
            'label' => 'ops_defaults.circuit_breaker_cooldown.label',
            'help' => 'ops_defaults.circuit_breaker_cooldown.help',
            'constraints' => $nonNegative,
        ]);
    }

    /**
     * PasswordToggle field with FormKit option merge (not Symfony PasswordType).
     *
     * @param array<string, mixed> $options
     */
    private function addTogglePassword(string $name, array $options): void
    {
        $this->boundBuilder()->add(
            $name,
            PasswordType::class,
            $this->mergeFieldOptions($name, 'password', $options),
        );
    }
}
