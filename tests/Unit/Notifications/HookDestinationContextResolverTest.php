<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Service\HookDestinationContextResolver;
use App\Project\Entity\Project;
use PHPUnit\Framework\TestCase;

final class HookDestinationContextResolverTest extends TestCase
{
    public function testEmptyUuidReturnsNull(): void
    {
        $repository = $this->createMock(NotificationDestinationRepository::class);
        $repository->expects(self::never())->method('findOneBy');

        $resolver = new HookDestinationContextResolver($repository);

        self::assertNull($resolver->resolve('', NotificationDestinationType::Slack));
    }

    public function testWrongTypeReturnsNull(): void
    {
        $destination = $this->destination(NotificationDestinationType::Teams, 'secret', new Project());
        $repository = $this->createStub(NotificationDestinationRepository::class);
        $repository->method('findOneBy')->willReturn($destination);

        $resolver = new HookDestinationContextResolver($repository);

        self::assertNull($resolver->resolve('dest-uuid', NotificationDestinationType::Slack));
    }

    public function testMissingSigningSecretReturnsNull(): void
    {
        $destination = $this->destination(NotificationDestinationType::Slack, null, new Project());
        $repository = $this->createStub(NotificationDestinationRepository::class);
        $repository->method('findOneBy')->willReturn($destination);

        $resolver = new HookDestinationContextResolver($repository);

        self::assertNull($resolver->resolve('dest-uuid', NotificationDestinationType::Slack));
    }

    public function testMissingProjectReturnsNull(): void
    {
        $destination = $this->destination(NotificationDestinationType::Slack, 'secret', null);
        $repository = $this->createStub(NotificationDestinationRepository::class);
        $repository->method('findOneBy')->willReturn($destination);

        $resolver = new HookDestinationContextResolver($repository);

        self::assertNull($resolver->resolve('dest-uuid', NotificationDestinationType::Slack));
    }

    public function testHappyPathReturnsContext(): void
    {
        $project = new Project();
        $destination = $this->destination(NotificationDestinationType::Slack, 'hook-secret', $project);
        $repository = $this->createMock(NotificationDestinationRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['uuid' => 'dest-uuid'])
            ->willReturn($destination);

        $resolver = new HookDestinationContextResolver($repository);
        $context = $resolver->resolve('dest-uuid', NotificationDestinationType::Slack);

        self::assertNotNull($context);
        self::assertSame($destination, $context->destination);
        self::assertSame($project, $context->project);
        self::assertSame('hook-secret', $context->signingSecret);
    }

    public function testResolveForProjectRejectsUuidMismatch(): void
    {
        $project = new Project();
        $destination = $this->destination(NotificationDestinationType::Teams, 'secret', $project);
        $repository = $this->createStub(NotificationDestinationRepository::class);
        $repository->method('findOneBy')->willReturn($destination);

        $resolver = new HookDestinationContextResolver($repository);

        self::assertNull($resolver->resolveForProject(
            'dest-uuid',
            NotificationDestinationType::Teams,
            'not-the-project-uuid',
        ));
    }

    public function testResolveForProjectAcceptsMatchingUuid(): void
    {
        $project = new Project();
        $destination = $this->destination(NotificationDestinationType::Teams, 's', $project);
        $repository = $this->createStub(NotificationDestinationRepository::class);
        $repository->method('findOneBy')->willReturn($destination);

        $resolver = new HookDestinationContextResolver($repository);
        $context = $resolver->resolveForProject(
            'dest-uuid',
            NotificationDestinationType::Teams,
            $project->getUuid(),
        );

        self::assertNotNull($context);
        self::assertSame($project, $context->project);
    }

    private function destination(
        NotificationDestinationType $type,
        ?string $secret,
        ?Project $project,
    ): NotificationDestination {
        $destination = new NotificationDestination();
        $destination->setType($type);
        $destination->setSigningSecret($secret);
        if ($project instanceof Project) {
            $destination->setProject($project);
        }

        return $destination;
    }
}
