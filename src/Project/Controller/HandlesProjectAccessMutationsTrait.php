<?php

declare(strict_types=1);

namespace App\Project\Controller;

use App\Project\Exception\ProjectAccessException;
use App\Project\Service\ProjectAccessFlashKeys;

/**
 * Shared try/catch + flash mapping for project membership mutations.
 *
 * Panel controllers typically deny on {@see ProjectAccessException::isForbidden()};
 * instance-admin controllers usually flash and continue.
 */
trait HandlesProjectAccessMutationsTrait
{
    /**
     * @param callable(): mixed $mutation
     */
    protected function attemptProjectAccessMutation(
        callable $mutation,
        string $successFlash,
        bool $denyOnForbidden = true,
    ): void {
        try {
            $mutation();
            $this->addFlash('success', $successFlash);
        } catch (ProjectAccessException $e) {
            if ($denyOnForbidden && $e->isForbidden()) {
                throw $this->createAccessDeniedException();
            }
            $this->addFlash('error', ProjectAccessFlashKeys::forException($e));
        }
    }
}
