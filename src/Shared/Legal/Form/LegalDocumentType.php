<?php

declare(strict_types=1);

namespace App\Shared\Legal\Form;

use App\Shared\Form\FormKitAbstractType;
use App\Shared\Legal\Entity\LegalDocument;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Title + CKEditor 5 body ({@code nowo-tech/ckeditor5-editor-bundle} via FormKit).
 */
final class LegalDocumentType extends FormKitAbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addTextField('title', [
                'constraints' => [new NotBlank(), new Length(max: 180)],
            ]);
            $this->addCkeditor5EditorField('body', [
                'required' => false,
                'config' => 'legal',
            ]);
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LegalDocument::class,
        ]);
    }
}
