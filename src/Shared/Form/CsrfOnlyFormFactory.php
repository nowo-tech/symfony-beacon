<?php

declare(strict_types=1);

namespace App\Shared\Form;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Creates lightweight CSRF-only forms for single POST actions.
 */
final readonly class CsrfOnlyFormFactory
{
    public function __construct(
        private FormFactoryInterface $formFactory,
    ) {
    }

    /**
     * @param bool   $named         When true, form name is `csrf_only` (nested `csrf_only[_token]`).
     *                              When false, empty block prefix → flat `_token` / custom field name (kit controllers).
     * @param string $csrfFieldName Symfony CSRF field name (`_token` or kit `_csrf_token`)
     */
    public function create(
        string $action,
        string $csrfTokenId,
        string $method = 'POST',
        bool $named = true,
        string $csrfFieldName = '_token',
    ): FormInterface {
        $options = [
            'action' => $action,
            'method' => strtoupper(trim($method)),
            'csrf_token_id' => $csrfTokenId,
            'csrf_field_name' => $csrfFieldName,
        ];

        if ($named) {
            return $this->formFactory->createNamed('csrf_only', CsrfOnlyType::class, null, $options);
        }

        return $this->formFactory->create(CsrfOnlyType::class, null, $options);
    }

    /**
     * CSRF form with typed flat fields (empty block prefix).
     *
     * @param array<string, scalar|null>              $fields         Field name => default value
     * @param array<string, string>                   $fieldTypes     Field name => FormKit snake type (default hidden)
     * @param array<string, array<string, mixed>>     $fieldOptions   Per-field Form Type options
     */
    public function createWithFields(
        string $action,
        string $csrfTokenId,
        array $fields,
        string $method = 'POST',
        string $csrfFieldName = '_token',
        array $fieldTypes = [],
        array $fieldOptions = [],
    ): FormInterface {
        $data = [];
        foreach ($fields as $name => $value) {
            $data[(string) $name] = null === $value ? '' : (string) $value;
        }

        return $this->formFactory->create(HiddenFieldsCsrfType::class, $data, [
            'action' => $action,
            'method' => strtoupper(trim($method)),
            'csrf_token_id' => $csrfTokenId,
            'csrf_field_name' => $csrfFieldName,
            'fields' => array_keys($data),
            'field_types' => $fieldTypes,
            'field_options' => $fieldOptions,
        ]);
    }
}
