<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectMembershipRepository;

/**
 * Resolves {@code @token} mentions in comment bodies to project-accessible users only.
 */
final readonly class IssueMentionParser
{
    public function __construct(
        private ProjectMembershipRepository $membershipRepository,
    ) {
    }

    /**
     * @return list<User> deduplicated mentioned users with project access (never the author)
     */
    public function resolveMentions(Project $project, string $body, ?User $author = null): array
    {
        $tokens = $this->extractTokens($body);
        if ([] === $tokens) {
            return [];
        }

        $candidates = $this->membershipRepository->findUsersByProject($project);
        $authorId = $author?->getId();
        $matched = [];

        foreach ($tokens as $token) {
            $user = $this->matchToken($token, $candidates);
            if (!$user instanceof User) {
                continue;
            }
            $id = $user->getId();
            if (null === $id || $id === $authorId) {
                continue;
            }
            $matched[$id] = $user;
        }

        return array_values($matched);
    }

    /**
     * @return list<string> lowercased unique mention tokens (without @)
     */
    public function extractTokens(string $body): array
    {
        if (!preg_match_all('/(^|[\s(,.;:!?\[])@([A-Za-z0-9._+\-]+)/u', $body, $matches)) {
            return [];
        }

        $tokens = [];
        foreach ($matches[2] as $raw) {
            $token = strtolower(trim($raw));
            if ('' !== $token) {
                $tokens[$token] = $token;
            }
        }

        return array_values($tokens);
    }

    /**
     * @param list<User> $candidates
     */
    private function matchToken(string $token, array $candidates): ?User
    {
        foreach ($candidates as $user) {
            $email = strtolower($user->getEmail());
            $local = strstr($email, '@', true) ?: $email;
            $display = strtolower(str_replace(' ', '', $user->getDisplayName()));
            $displaySpaced = strtolower($user->getDisplayName());

            if ($token === $email
                || $token === $local
                || $token === $display
                || $token === str_replace(' ', '.', $displaySpaced)
                || $token === str_replace(' ', '_', $displaySpaced)
            ) {
                return $user;
            }
        }

        return null;
    }
}
