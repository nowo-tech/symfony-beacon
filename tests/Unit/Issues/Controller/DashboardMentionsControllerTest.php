<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Controller;

use App\Issues\Controller\DashboardMentionsController;
use App\Issues\Repository\IssueMentionRepository;
use App\Issues\Service\DashboardMentionsFilterResolver;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\AccessibleProjectsProvider;
use App\Shared\Form\GetFilterFormFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class DashboardMentionsControllerTest extends TestCase
{
    public function testRedirectQueryKeepsNonEmptyFilterFields(): void
    {
        $controller = new DashboardMentionsController(
            $this->createStub(IssueMentionRepository::class),
            $this->createStub(EntityManagerInterface::class),
            new DashboardMentionsFilterResolver(
                new AccessibleProjectsProvider(
                    $this->createStub(ProjectRepository::class),
                    new RequestStack(),
                ),
            ),
            new GetFilterFormFactory($this->createStub(FormFactoryInterface::class)),
        );
        $method = new ReflectionMethod(DashboardMentionsController::class, 'redirectQuery');

        $project = $this->createStub(FormInterface::class);
        $project->method('getData')->willReturn('11111111-1111-4111-8111-111111111111');
        $unread = $this->createStub(FormInterface::class);
        $unread->method('getData')->willReturn('1');
        $perPage = $this->createStub(FormInterface::class);
        $perPage->method('getData')->willReturn('');
        $missing = $this->createStub(FormInterface::class);

        $form = $this->createStub(FormInterface::class);
        $form->method('has')->willReturnCallback(static fn (string $name): bool => 'project' === $name || 'unread' === $name || 'per_page' === $name);
        $form->method('get')->willReturnCallback(static function (string $name) use ($project, $unread, $perPage, $missing): FormInterface {
            return match ($name) {
                'project' => $project,
                'unread' => $unread,
                'per_page' => $perPage,
                default => $missing,
            };
        });

        self::assertSame([
            'project' => '11111111-1111-4111-8111-111111111111',
            'unread' => '1',
        ], $method->invoke($controller, $form));
    }
}
