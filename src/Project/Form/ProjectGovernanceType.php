<?php

declare(strict_types=1);

namespace App\Project\Form;

use Override;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

/**
 * Project governance overrides with optional non-negative numeric fields.
 */
final class ProjectGovernanceType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            foreach ([
                'retention_days',
                'retention_max_events',
                'ingest_rate_limit_per_minute',
                'event_quota_daily',
                'event_quota_monthly',
            ] as $field) {
                $this->addIntegerField($field, [
                    'required' => false,
                    'label' => false,
                    'constraints' => [new PositiveOrZero()],
                    'attr' => ['min' => 0],
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'project_governance',
            'translation_domain' => 'messages',
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return '';
    }
}
