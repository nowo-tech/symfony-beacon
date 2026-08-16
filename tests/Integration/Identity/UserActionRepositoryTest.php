<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Entity\User;
use App\Identity\Entity\UserAction;
use App\Identity\Entity\UserGroup;
use App\Identity\Repository\UserActionRepository;
use App\Identity\UserActionType;
use App\Project\Entity\Project;
use App\Tests\Support\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionProperty;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserActionRepositoryTest extends DatabaseWebTestCase
{
    public function testQueriesUserProjectAndGroupActivity(): void
    {
        [, $owner, $project] = $this->bootWithDemoProject('activity-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $repository = self::getContainer()->get(UserActionRepository::class);

        $member = (new User())
            ->setEmail('activity-member@example.com')
            ->setDisplayName('Activity Member')
            ->setPassword($hasher->hashPassword(new User(), 'secret'));
        $group = (new UserGroup())
            ->setName('Operators')
            ->setSlug('operators');
        $otherProject = (new Project())
            ->setName('Other project')
            ->setSlug('other-project');
        $em->persist($member);
        $em->persist($group);
        $em->persist($otherProject);
        $em->flush();

        $projectOpened = $this->action(UserActionType::ProjectOpened, $owner, $owner, ['project_uuid' => $project->getUuid()], new DateTimeImmutable('2026-08-16 10:00:00'));
        $settingsViewed = $this->action(UserActionType::ProjectSettingsViewed, $owner, $member, ['project_uuid' => $project->getUuid()], new DateTimeImmutable('2026-08-16 11:00:00'));
        $groupAdded = $this->action(UserActionType::GroupMemberAdded, $owner, $member, ['group_uuid' => $group->getUuid()], new DateTimeImmutable('2026-08-16 11:30:00'));
        $otherProjectOpened = $this->action(UserActionType::ProjectOpened, $owner, $member, ['project_uuid' => $otherProject->getUuid()], new DateTimeImmutable('2026-08-16 12:00:00'));
        $passwordReset = $this->action(UserActionType::PasswordResetRequested, $member, $owner, ['project_uuid' => $project->getUuid()], new DateTimeImmutable('2026-08-16 12:30:00'));
        $em->persist($projectOpened);
        $em->persist($settingsViewed);
        $em->persist($groupAdded);
        $em->persist($otherProjectOpened);
        $em->persist($passwordReset);
        $em->flush();

        self::assertSame([], $repository->findForUser($owner, [UserActionType::ProjectOpened], UserActionType::ProjectSettingsViewed));
        self::assertSame(
            [$settingsViewed->getId()],
            array_map(
                static fn (UserAction $action): ?int => $action->getId(),
                $repository->findForUser(
                    $member,
                    [UserActionType::ProjectOpened, UserActionType::ProjectSettingsViewed],
                    UserActionType::ProjectSettingsViewed,
                    new DateTimeImmutable('2026-08-16 10:30:00'),
                    new DateTimeImmutable('2026-08-16 11:30:00'),
                    10,
                ),
            ),
        );

        self::assertSame([], $repository->findActorProductActivity(new User(), [UserActionType::ProjectOpened], [$project->getUuid()]));
        self::assertSame([], $repository->findActorProductActivity($owner, [], [$project->getUuid()]));
        self::assertSame(0, $repository->countActorProductActivity($owner, [], [$project->getUuid()]));
        self::assertSame(
            [$settingsViewed->getId(), $projectOpened->getId()],
            array_map(
                static fn (UserAction $action): ?int => $action->getId(),
                $repository->findActorProductActivity(
                    $owner,
                    [UserActionType::ProjectOpened, UserActionType::ProjectSettingsViewed],
                    [$project->getUuid()],
                    10,
                    0,
                ),
            ),
        );
        self::assertSame(2, $repository->countActorProductActivity($owner, [UserActionType::ProjectOpened, UserActionType::ProjectSettingsViewed], [$project->getUuid()]));
        self::assertSame(
            [$passwordReset->getId(), $otherProjectOpened->getId()],
            array_map(static fn (UserAction $action): ?int => $action->getId(), $repository->findLatest(2)),
        );
        self::assertSame(
            [$settingsViewed->getId(), $projectOpened->getId()],
            array_map(
                static fn (UserAction $action): ?int => $action->getId(),
                $repository->findForProject(
                    $project,
                    [UserActionType::ProjectOpened, UserActionType::ProjectSettingsViewed],
                    null,
                    new DateTimeImmutable('2026-08-16 09:00:00'),
                    new DateTimeImmutable('2026-08-16 12:00:00'),
                    10,
                ),
            ),
        );
        self::assertSame(
            [$groupAdded->getId()],
            array_map(
                static fn (UserAction $action): ?int => $action->getId(),
                $repository->findForGroup($group, [UserActionType::GroupMemberAdded], UserActionType::GroupMemberAdded),
            ),
        );
        self::assertSame([], $repository->findForProject($project, []));
    }

    private function action(UserActionType $type, ?User $actor, ?User $subject, array $context, DateTimeImmutable $createdAt): UserAction
    {
        $action = (new UserAction())
            ->setAction($type)
            ->setActor($actor)
            ->setSubjectUser($subject)
            ->setContext($context);
        new ReflectionProperty(UserAction::class, 'createdAt')->setValue($action, $createdAt);

        return $action;
    }
}
