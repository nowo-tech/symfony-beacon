<?php

declare(strict_types=1);

namespace App\Shared\Legal\Service;

use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Renders the built-in legal seed (Twig + translations) as HTML for the editor.
 *
 * Saving that HTML stores an operator copy. Until then the public page keeps using the templates.
 */
final readonly class LegalBuiltinHtml
{
    public function __construct(
        private Environment $twig,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param 'notice'|'privacy'|'terms'|'cookies' $slug
     */
    public function title(string $slug, string $locale): string
    {
        return $this->translator->trans('legal.'.$slug.'.title', locale: $locale);
    }

    /**
     * @param 'notice'|'privacy'|'terms'|'cookies' $slug
     */
    public function body(string $slug, string $locale): string
    {
        $previous = $this->translator->getLocale();
        if ($this->translator instanceof LocaleAwareInterface) {
            $this->translator->setLocale($locale);
        }

        try {
            return $this->twig->render($this->template($slug), [
                'document_locale' => $locale,
            ]);
        } finally {
            if ($this->translator instanceof LocaleAwareInterface) {
                $this->translator->setLocale($previous);
            }
        }
    }

    /**
     * @param 'notice'|'privacy'|'terms'|'cookies' $slug
     */
    private function template(string $slug): string
    {
        return match ($slug) {
            'notice' => 'legal/_notice_body.html.twig',
            'privacy' => 'legal/_privacy_body.html.twig',
            'terms' => 'legal/_terms_body.html.twig',
            'cookies' => 'legal/_cookies_body.html.twig',
        };
    }
}
