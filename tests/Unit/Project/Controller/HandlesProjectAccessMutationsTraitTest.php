<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Controller;

use App\Project\Controller\HandlesProjectAccessMutationsTrait;
use App\Project\Exception\ProjectAccessException;
use App\Project\Service\ProjectAccessFlashKeys;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class HandlesProjectAccessMutationsTraitTest extends TestCase
{
    public function testSuccessAddsFlash(): void
    {
        $controller = new class {
            use HandlesProjectAccessMutationsTrait;

            /** @var list<array{0: string, 1: string}> */
            public array $flashes = [];

            public function run(callable $mutation): void
            {
                $this->attemptProjectAccessMutation($mutation, 'flash.ok');
            }

            protected function addFlash(string $type, mixed $message): void
            {
                $this->flashes[] = [$type, (string) $message];
            }

            protected function createAccessDeniedException(string $message = 'Access Denied.', ?\Throwable $previous = null): AccessDeniedHttpException
            {
                return new AccessDeniedHttpException($message, $previous);
            }
        };

        $controller->run(static function (): void {});
        self::assertSame([['success', 'flash.ok']], $controller->flashes);
    }

    public function testForbiddenThrowsWhenConfigured(): void
    {
        $controller = new class {
            use HandlesProjectAccessMutationsTrait;

            public function run(): void
            {
                $this->attemptProjectAccessMutation(
                    static fn () => throw ProjectAccessException::of(ProjectAccessException::FORBIDDEN),
                    'flash.ok',
                );
            }

            protected function addFlash(string $type, mixed $message): void
            {
            }

            protected function createAccessDeniedException(string $message = 'Access Denied.', ?\Throwable $previous = null): AccessDeniedHttpException
            {
                return new AccessDeniedHttpException($message, $previous);
            }
        };

        $this->expectException(AccessDeniedHttpException::class);
        $controller->run();
    }

    public function testDomainErrorFlashesWhenNotDenied(): void
    {
        $controller = new class {
            use HandlesProjectAccessMutationsTrait;

            /** @var list<array{0: string, 1: string}> */
            public array $flashes = [];

            public function run(): void
            {
                $this->attemptProjectAccessMutation(
                    static fn () => throw ProjectAccessException::of(ProjectAccessException::LAST_OWNER),
                    'flash.ok',
                    denyOnForbidden: false,
                );
            }

            protected function addFlash(string $type, mixed $message): void
            {
                $this->flashes[] = [$type, (string) $message];
            }

            protected function createAccessDeniedException(string $message = 'Access Denied.', ?\Throwable $previous = null): AccessDeniedHttpException
            {
                return new AccessDeniedHttpException($message, $previous);
            }
        };

        $controller->run();
        self::assertSame([
            ['error', ProjectAccessFlashKeys::forException(ProjectAccessException::of(ProjectAccessException::LAST_OWNER))],
        ], $controller->flashes);
    }
}
