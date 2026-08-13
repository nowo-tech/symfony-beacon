<?php

declare(strict_types=1);

namespace App\Issues\Form;

use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Save the current issues filter set under a user-defined name (FormKit {@code beacon}).
 *
 * Catalogue: {@code translations/form.*.yaml} → {@code issue_saved_view.*}.
 * Hidden query fields disable FormKit auto placeholder/help keys.
 */
final class IssueSavedViewType extends FormKitAbstractType
{
    /** @var list<string> */
    public const array QUERY_KEYS = [
        'q',
        'level',
        'status',
        'environment',
        'release',
        'compare',
        'assignee',
        'priority',
        'tag',
        'url',
        'user',
        'sort',
        'dir',
        'per_page',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addTextField('name', [
                'help' => false,
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 80),
                ],
                'attr' => [
                    'maxlength' => 80,
                ],
                'label_attr' => ['class' => 'block text-xs text-[var(--color-ink)]/60 mb-1'],
                'row_attr' => ['class' => 'flex flex-col'],
            ]);

            foreach (self::QUERY_KEYS as $key) {
                $this->addNamedField($key, 'hidden', [
                    'required' => false,
                    'empty_data' => '',
                    'label' => false,
                    'placeholder' => false,
                    'help' => false,
                ]);
            }
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'issue_view_save',
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'issue_saved_view';
    }
}
