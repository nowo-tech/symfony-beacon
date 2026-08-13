<?php

declare(strict_types=1);

namespace App\Shared\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

/**
 * Shared JSON upload form for portability/import screens.
 */
abstract class AbstractJsonImportType extends FormKitAbstractType
{
    final public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addNamedField($this->fileFieldName(), 'file', [
                'placeholder' => false,
                'help' => false,
                'required' => true,
                'mapped' => false,
                'constraints' => [
                    new File(
                        mimeTypes: [
                            'application/json',
                            'application/*+json',
                            'text/json',
                        ],
                        mimeTypesMessage: 'Upload a JSON file.',
                    ),
                ],
                'attr' => [
                    'accept' => 'application/json,.json',
                    'data-testid' => $this->fileInputTestId(),
                ],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => $this->csrfTokenId(),
        ]);
    }

    abstract protected function fileFieldName(): string;

    abstract protected function fileInputTestId(): string;

    abstract protected function csrfTokenId(): string;
}
