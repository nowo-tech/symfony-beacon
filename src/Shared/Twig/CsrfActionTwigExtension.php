<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use Nowo\FormKitBundle\Form\CsrfOnlyFormFactory;
use Nowo\FormKitBundle\Form\Type\HiddenFieldsCsrfType;
use Nowo\FormKitBundle\Form\Type\SearchQueryType;
use Override;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig helpers for CSRF-only and GET search forms in shared includes / kit forks.
 */
final class CsrfActionTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly CsrfOnlyFormFactory $csrfOnlyFormFactory,
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('csrf_action_form', $this->csrfActionForm(...)),
            new TwigFunction('search_query_form', $this->searchQueryForm(...)),
            new TwigFunction('flat_hidden_fields', $this->flatHiddenFields(...)),
        ];
    }

    /**
     * @param bool                                $named         Nested `csrf_only[_token]` when true; flat token field when false (kits)
     * @param string                              $csrfFieldName `_token` or kit `_csrf_token`
     * @param array<string, scalar|null>          $fields        Optional typed fields (flat names; forces empty-prefix form)
     * @param array<string, string>               $fieldTypes    Field name => FormKit snake type (default hidden)
     * @param array<string, array<string, mixed>> $fieldOptions  Per-field Form Type options
     */
    public function csrfActionForm(
        string $action,
        string $csrfTokenId,
        string $method = 'POST',
        bool $named = true,
        string $csrfFieldName = '_token',
        array $fields = [],
        array $fieldTypes = [],
        array $fieldOptions = [],
    ): FormView {
        if ([] !== $fields) {
            return $this->csrfOnlyFormFactory
                ->createWithFields($action, $csrfTokenId, $fields, $method, $csrfFieldName, $fieldTypes, $fieldOptions)
                ->createView();
        }

        if ($named) {
            return $this->csrfOnlyFormFactory
                ->createNamed($action, $csrfTokenId, $method, $csrfFieldName)
                ->createView();
        }

        return $this->csrfOnlyFormFactory
            ->create($action, $csrfTokenId, $method, $csrfFieldName)
            ->createView();
    }

    /**
     * Rootless hidden fields (no CSRF) for kit UI flags like {@code _section} / {@code _modal}.
     *
     * Controllers often read these as top-level request keys (not nested under the main form name).
     *
     * @param array<string, scalar|null> $fields
     */
    public function flatHiddenFields(array $fields): FormView
    {
        $data = [];
        foreach ($fields as $name => $value) {
            $data[(string) $name] = null === $value ? '' : (string) $value;
        }

        return $this->formFactory->create(HiddenFieldsCsrfType::class, $data, [
            'csrf_protection' => false,
            'fields' => array_keys($data),
        ])->createView();
    }

    /**
     * @param array<string, scalar|null> $inputAttr
     */
    public function searchQueryForm(string $action, string $q = '', array $inputAttr = []): FormView
    {
        return $this->formFactory->create(SearchQueryType::class, null, [
            'action' => $action,
            'q' => $q,
            'input_attr' => $inputAttr,
        ])->createView();
    }
}
