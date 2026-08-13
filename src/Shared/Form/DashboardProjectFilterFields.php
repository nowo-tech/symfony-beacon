<?php

declare(strict_types=1);

namespace App\Shared\Form;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Shared page / project / per_page fields for dashboard GET filter forms.
 */
final class DashboardProjectFilterFields
{
    /** @var list<int> */
    public const array PER_PAGE_SIZES = [10, 25, 50, 100];

    /**
     * @return array<string, int>
     */
    public static function perPageChoices(TranslatorInterface $translator): array
    {
        $perPageChoices = [];
        foreach (self::PER_PAGE_SIZES as $size) {
            $perPageChoices[$translator->trans('issues.filter.per_page_option', ['%count%' => (string) $size])] = $size;
        }

        return $perPageChoices;
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, string>       $projectChoices
     */
    public static function addPageAndProject(
        FormBuilderInterface $builder,
        TranslatorInterface $translator,
        array $projectChoices,
        string $projectPlaceholderKey,
        string $projectAriaLabelKey,
    ): void {
        $builder
            ->add('page', HiddenType::class)
            ->add('project', ChoiceType::class, [
                'label' => false,
                'required' => false,
                'choices' => $projectChoices,
                'choice_translation_domain' => false,
                'placeholder' => $translator->trans($projectPlaceholderKey),
                'attr' => [
                    'class' => 'input',
                    'aria-label' => $projectAriaLabelKey,
                ],
            ]);
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     */
    public static function addPerPage(
        FormBuilderInterface $builder,
        TranslatorInterface $translator,
    ): void {
        $builder->add('per_page', ChoiceType::class, [
            'label' => false,
            'required' => true,
            'choices' => self::perPageChoices($translator),
            'choice_translation_domain' => false,
            'placeholder' => false,
            'attr' => [
                'class' => 'input',
                'aria-label' => 'issues.filter.per_page',
            ],
        ]);
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, string>       $projectChoices
     */
    public static function addPageProjectAndPerPage(
        FormBuilderInterface $builder,
        TranslatorInterface $translator,
        array $projectChoices,
        string $projectPlaceholderKey,
        string $projectAriaLabelKey,
    ): void {
        self::addPageAndProject(
            $builder,
            $translator,
            $projectChoices,
            $projectPlaceholderKey,
            $projectAriaLabelKey,
        );
        self::addPerPage($builder, $translator);
    }
}
