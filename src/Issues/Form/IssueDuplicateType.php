<?php

declare(strict_types=1);

namespace App\Issues\Form;

use App\Shared\Form\FormKitAbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * CSRF-protected issue duplicate / merge form.
 *
 * {@code query} is an unmapped combobox search widget (Stimulus); submitted value is {@code canonical_uuid}.
 */
final class IssueDuplicateType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addNamedField('canonical_uuid', 'hidden', [
                'placeholder' => false,
                'constraints' => [
                    new NotBlank(),
                ],
                'attr' => [
                    'data-combobox-target' => 'value',
                ],
            ]);
            $this->boundBuilder()->add('query', SearchType::class, $this->mergeFieldOptions('query', 'search', [
                'mapped' => false,
                'required' => false,
                'placeholder' => false,
                'help' => false,
                'label' => 'issues.duplicate_canonical',
                'label_attr' => [
                    'class' => 'confirm-dialog__label',
                ],
                'attr' => [
                    'class' => 'input w-full',
                    'autocomplete' => 'off',
                    'placeholder' => 'issues.duplicate_search_placeholder',
                    'data-combobox-target' => 'query',
                    'data-confirm-dialog-target' => 'confirmInput',
                    'data-action' => 'input->combobox#filter focus->combobox#onQueryFocus keydown->combobox#onQueryKeydown',
                    'aria-autocomplete' => 'list',
                    'aria-controls' => 'issue-duplicate-options',
                ],
            ]));
            $this->addCheckboxField('merge_events', [
                'required' => false,
                'placeholder' => false,
                'label' => 'issues.merge_events_label',
                'help' => 'issues.merge_events_help',
                'attr' => [
                    'class' => 'checkbox',
                ],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'issue_duplicate',
        ]);
    }
}
