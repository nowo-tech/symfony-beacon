<?php

declare(strict_types=1);

namespace App\Project\Form;

use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

/**
 * Project governance overrides (FormKit {@code beacon}).
 *
 * Catalogue: {@code translations/form.*.yaml} → {@code project_governance.*}.
 * Placeholders are instance default literals (not catalogue keys).
 */
final class ProjectGovernanceType extends FormKitAbstractType
{
    /** @var list<string> */
    private const array FIELDS = [
        'retention_days',
        'retention_max_events',
        'ingest_rate_limit_per_minute',
        'event_quota_daily',
        'event_quota_monthly',
    ];

    /** Field name → {@see ProjectGovernanceResolver::envDefaults()} key. */
    private const array DEFAULT_KEYS = [
        'retention_days' => 'retention_days',
        'retention_max_events' => 'retention_max_events',
        'ingest_rate_limit_per_minute' => 'ingest_rate_limit',
        'event_quota_daily' => 'event_quota_daily',
        'event_quota_monthly' => 'event_quota_monthly',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, int|string|null> $defaults */
        $defaults = $options['env_defaults'];

        $this->withBuilder($builder, function () use ($defaults): void {
            foreach (self::FIELDS as $field) {
                $defaultKey = self::DEFAULT_KEYS[$field];
                $default = $defaults[$defaultKey] ?? '';
                $this->addIntegerField($field, [
                    'help_translation_parameters' => ['%default%' => $default],
                    'required' => false,
                    'constraints' => [new PositiveOrZero()],
                    'attr' => [
                        'min' => 0,
                        'placeholder' => (string) $default,
                    ],
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'project_governance',
            'env_defaults' => [],
        ]);
        $resolver->setAllowedTypes('env_defaults', 'array');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'project_governance';
    }
}
