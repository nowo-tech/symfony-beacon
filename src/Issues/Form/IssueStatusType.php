<?php

declare(strict_types=1);

namespace App\Issues\Form;

use App\Issues\Enum\IssueStatus;
use App\Shared\Form\FormKitAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * CSRF-protected issue status mutation form.
 */
final class IssueStatusType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $statusValues = array_map(static fn (IssueStatus $status): string => $status->value, IssueStatus::cases());

        $this->withBuilder($builder, function () use ($statusValues): void {
            $this->addNamedField('status', 'hidden', [
                'placeholder' => false,
                'constraints' => [
                    new NotBlank(),
                    new Choice(choices: $statusValues),
                ],
            ]);
        });
    }
}
