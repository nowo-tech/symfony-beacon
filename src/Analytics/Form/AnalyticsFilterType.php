<?php

declare(strict_types=1);

namespace App\Analytics\Form;

use App\Shared\Form\AbstractGetFilterType;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Per-project analytics filters (FormKit {@code filter}: no labels; placeholder + help).
 *
 * Catalogue: {@code translations/form.*.yaml} → {@code analytics_filter.*}.
 * Twig: {@code form/_fields.html.twig} ({@code form_row} + help; no visible captions).
 */
final class AnalyticsFilterType extends AbstractGetFilterType
{
    private const string FIELD_ROW_CLASS = 'analytics-filters__field';

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
        $this->withBuilder($builder, function (): void {
            $this->addTextField('from', [
                'attr' => [
                    'type' => 'date',
                    'aria-label' => $this->translator->trans('analytics.period.from', [], 'form'),
                ],
                'row_attr' => ['class' => self::FIELD_ROW_CLASS],
            ]);
            $this->addTextField('to', [
                'attr' => [
                    'type' => 'date',
                    'aria-label' => $this->translator->trans('analytics.period.to', [], 'form'),
                ],
                'row_attr' => ['class' => self::FIELD_ROW_CLASS],
            ]);
            $this->addHiddenFilterField('period');
            $this->addTextField('environment', [
                'attr' => [
                    'autocomplete' => 'off',
                    'aria-label' => $this->translator->trans('analytics.filter.environment', [], 'form'),
                ],
                'row_attr' => ['class' => self::FIELD_ROW_CLASS],
            ]);
            $this->addTextField('release', [
                'attr' => [
                    'autocomplete' => 'off',
                    'aria-label' => $this->translator->trans('analytics.filter.release', [], 'form'),
                ],
                'row_attr' => ['class' => self::FIELD_ROW_CLASS],
            ]);
            $this->addTextField('level', [
                'attr' => [
                    'autocomplete' => 'off',
                    'aria-label' => $this->translator->trans('analytics.filter.level', [], 'form'),
                ],
                'row_attr' => ['class' => self::FIELD_ROW_CLASS],
            ]);
        });
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'analytics_filter';
    }
}
