<?php

declare(strict_types=1);

namespace App\Issues\Twig;

use App\Issues\Dto\QueryFacts;
use App\Issues\Service\IssueStackPresenter;
use App\Issues\Service\QueryFactsExtractor;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Issue/event payload helpers (Query facts + stack presentation).
 */
final class IssueEventTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly QueryFactsExtractor $queryFactsExtractor,
        private readonly IssueStackPresenter $stackPresenter,
    ) {
    }

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('beacon_query_facts', $this->queryFacts(...)),
            new TwigFunction('beacon_stack_frames', $this->stackFrames(...)),
        ];
    }

    /**
     * @param mixed $payload Envelope event payload
     */
    public function queryFacts(mixed $payload): ?QueryFacts
    {
        if (!\is_array($payload)) {
            return null;
        }

        return $this->queryFactsExtractor->extract($payload);
    }

    /**
     * @return list<array{frame: array<string, mixed>, open: bool}>
     */
    public function stackFrames(mixed $frames): array
    {
        return $this->stackPresenter->displayFrames($frames);
    }
}
