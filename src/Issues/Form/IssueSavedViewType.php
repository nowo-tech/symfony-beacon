<?php

declare(strict_types=1);

namespace App\Issues\Form;

use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Save the current issues filter set under a user-defined name.
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
                'label' => false,
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 80),
                ],
                'attr' => [
                    'class' => 'input',
                    'maxlength' => 80,
                ],
            ]);

            foreach (self::QUERY_KEYS as $key) {
                $this->addNamedField($key, 'hidden', [
                    'required' => false,
                    'empty_data' => '',
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'issue_view_save',
        ]);
    }
}
