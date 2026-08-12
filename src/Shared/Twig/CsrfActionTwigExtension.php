<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use App\Shared\Form\CsrfOnlyFormFactory;
use App\Shared\Form\SearchQueryType;
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
        ];
    }

    /**
     * @param bool                       $named         Nested `csrf_only[_token]` when true; flat token field when false (kits)
     * @param string                     $csrfFieldName `_token` or kit `_csrf_token`
     * @param array<string, scalar|null> $fields        Optional typed hidden fields (flat names; forces empty-prefix form)
     */
    public function csrfActionForm(
        string $action,
        string $csrfTokenId,
        string $method = 'POST',
        bool $named = true,
        string $csrfFieldName = '_token',
        array $fields = [],
    ): FormView {
        if ([] !== $fields) {
            return $this->csrfOnlyFormFactory
                ->createWithFields($action, $csrfTokenId, $fields, $method)
                ->createView();
        }

        return $this->csrfOnlyFormFactory
            ->create($action, $csrfTokenId, $method, $named, $csrfFieldName)
            ->createView();
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
