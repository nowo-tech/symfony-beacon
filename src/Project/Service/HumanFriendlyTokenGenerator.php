<?php

declare(strict_types=1);

namespace App\Project\Service;

/**
 * Builds short, pronounceable tokens for API key labels / public keys.
 *
 * Format: {adjective}-{noun}-{hex} (e.g. calm-otter-a3f2).
 */
final class HumanFriendlyTokenGenerator
{
    /** @var list<string> */
    private const array ADJECTIVES = [
        'amber', 'bold', 'calm', 'clear', 'crisp', 'eager', 'fair', 'fleet',
        'gentle', 'glad', 'golden', 'keen', 'kind', 'lively', 'lucid', 'merry',
        'neat', 'noble', 'plaid', 'plucky', 'proud', 'quick', 'quiet', 'rapid',
        'ready', 'silver', 'sleek', 'solid', 'steady', 'sunny', 'swift', 'tidy',
        'vivid', 'warm', 'wise', 'brisk', 'cedar', 'coral', 'cosmic', 'delta',
    ];

    /** @var list<string> */
    private const array NOUNS = [
        'anchor', 'beacon', 'breeze', 'brook', 'canyon', 'cedar', 'comet', 'crane',
        'delta', 'ember', 'falcon', 'fjord', 'forest', 'glacier', 'harbor', 'heron',
        'island', 'lagoon', 'maple', 'meadow', 'nebula', 'orchid', 'otter', 'pine',
        'prairie', 'quarry', 'raven', 'river', 'sable', 'sierra', 'sparrow', 'summit',
        'tide', 'timber', 'valley', 'willow', 'zephyr', 'aurora', 'badger', 'orchid',
    ];

    /**
     * Human-friendly public key token with a short random suffix for uniqueness.
     */
    public function generateKey(int $suffixBytes = 3): string
    {
        return $this->baseName().'-'.bin2hex(random_bytes(max(2, $suffixBytes)));
    }

    /**
     * Label suggestion without hex suffix (e.g. calm-otter).
     */
    public function generateLabel(): string
    {
        return $this->baseName();
    }

    /**
     * @return list<string>
     */
    public function adjectiveWordList(): array
    {
        return self::ADJECTIVES;
    }

    /**
     * @return list<string>
     */
    public function nounWordList(): array
    {
        return self::NOUNS;
    }

    private function baseName(): string
    {
        $adjective = self::ADJECTIVES[random_int(0, \count(self::ADJECTIVES) - 1)];
        $noun = self::NOUNS[random_int(0, \count(self::NOUNS) - 1)];

        return $adjective.'-'.$noun;
    }
}
