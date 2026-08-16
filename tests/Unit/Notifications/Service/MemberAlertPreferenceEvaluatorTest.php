<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Service;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\Repository\IssueMentionRepository;
use App\Notifications\Entity\MemberAccountAlertEvent;
use App\Notifications\Entity\MemberProjectAlertEvent;
use App\Notifications\Entity\MemberProjectAlertPreference;
use App\Notifications\Enum\MemberAlertEvent;
use App\Notifications\Enum\MemberAlertScope;
use App\Notifications\Repository\MemberAccountAlertEventRepository;
use App\Notifications\Repository\MemberProjectAlertEventRepository;
use App\Notifications\Repository\MemberProjectAlertPreferenceRepository;
use App\Notifications\Service\MemberAlertPreferenceEvaluator;
use App\Project\Entity\Project;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class MemberAlertPreferenceEvaluatorTest extends TestCase
{
    public function testShouldNotifyHonorsGlobalProjectAndScopeRules(): void
    {
        $user = $this->user(1, 'alerts@example.com');
        $project = new Project()->setName('Demo')->setSlug('demo');
        $issue = new Issue()->setProject($project);

        $projectPrefs = $this->createStub(MemberProjectAlertPreferenceRepository::class);
        $projectPrefs->method('findOneByUserAndProject')->willReturn(new MemberProjectAlertPreference()->setUser($user)->setProject($project)->setEnabled(false));
        $evaluator = new MemberAlertPreferenceEvaluator(
            $projectPrefs,
            $this->createStub(MemberAccountAlertEventRepository::class),
            $this->createStub(MemberProjectAlertEventRepository::class),
            $this->createStub(IssueMentionRepository::class),
        );
        self::assertFalse($evaluator->shouldNotify($user, $project, $issue, MemberAlertEvent::IssueAssigned));

        $projectPrefs = $this->createStub(MemberProjectAlertPreferenceRepository::class);
        $projectEvents = $this->createStub(MemberProjectAlertEventRepository::class);
        $projectEvents->method('findOneByUserProjectAndEvent')->willReturn(new MemberProjectAlertEvent()
            ->setUser($user)
            ->setProject($project)
            ->setEvent(MemberAlertEvent::IssueAssigned)
            ->setScope(MemberAlertScope::Involved));
        $mentions = $this->createStub(IssueMentionRepository::class);
        $mentions->method('isUserMentionedOnIssue')->willReturn(true);
        $evaluator = new MemberAlertPreferenceEvaluator(
            $projectPrefs,
            $this->createStub(MemberAccountAlertEventRepository::class),
            $projectEvents,
            $mentions,
        );
        self::assertTrue($evaluator->shouldNotify($user, $project, $issue, MemberAlertEvent::IssueAssigned));
    }

    public function testFilterEligibleUsersCoversRowBasedBranches(): void
    {
        $project = new Project()->setName('Demo')->setSlug('demo');
        $issue = new Issue()->setProject($project);

        $mentioned = $this->user(1, 'mentioned@example.com');
        $disabled = $this->user(2, 'disabled@example.com')->setMemberAlertsEnabled(false);
        $projectDisabled = $this->user(3, 'project-disabled@example.com');
        $accountDisabled = $this->user(4, 'account-disabled@example.com');
        $allScope = $this->user(5, 'all@example.com');
        $issue->setAssignee($allScope);
        $withoutId = new User()->setEmail('no-id@example.com');

        $projectPref = new MemberProjectAlertPreference()->setUser($projectDisabled)->setProject($project)->setEnabled(false);
        $accountRow = new MemberAccountAlertEvent()->setUser($accountDisabled)->setEvent(MemberAlertEvent::IssueAssigned)->setEnabled(false);
        $projectRow = new MemberProjectAlertEvent()->setUser($allScope)->setProject($project)->setEvent(MemberAlertEvent::IssueAssigned)->setScope(MemberAlertScope::All);

        $projectPrefs = $this->createStub(MemberProjectAlertPreferenceRepository::class);
        $projectPrefs->method('findIndexedByUserIdsForProject')->willReturn([3 => $projectPref]);
        $accountEvents = $this->createStub(MemberAccountAlertEventRepository::class);
        $accountEvents->method('findIndexedByUserIds')->willReturn([
            4 => [MemberAlertEvent::IssueAssigned->value => $accountRow],
        ]);
        $projectEvents = $this->createStub(MemberProjectAlertEventRepository::class);
        $projectEvents->method('findIndexedByUserIdsForProject')->willReturn([
            5 => [MemberAlertEvent::IssueAssigned->value => $projectRow],
        ]);
        $mentions = $this->createStub(IssueMentionRepository::class);
        $mentions->method('findUserIdsMentionedOnIssue')->willReturn([1]);

        $evaluator = new MemberAlertPreferenceEvaluator($projectPrefs, $accountEvents, $projectEvents, $mentions);

        self::assertSame([], $evaluator->filterEligibleUsers([], $project, $issue, MemberAlertEvent::IssueAssigned));
        self::assertSame(
            [$mentioned, $allScope],
            $evaluator->filterEligibleUsers([$mentioned, $disabled, $projectDisabled, $accountDisabled, $allScope, $withoutId], $project, $issue, MemberAlertEvent::IssueAssigned),
        );
    }

    public function testFilterEligibleUsersReturnsEarlyWhenNoEnabledUsersRemain(): void
    {
        $project = new Project()->setName('Demo')->setSlug('demo');
        $issue = new Issue()->setProject($project);
        $disabled = $this->user(2, 'disabled@example.com')->setMemberAlertsEnabled(false);
        $withoutId = new User()->setEmail('no-id@example.com');

        $evaluator = new MemberAlertPreferenceEvaluator(
            $this->createStub(MemberProjectAlertPreferenceRepository::class),
            $this->createStub(MemberAccountAlertEventRepository::class),
            $this->createStub(MemberProjectAlertEventRepository::class),
            $this->createStub(IssueMentionRepository::class),
        );

        self::assertSame([], $evaluator->filterEligibleUsers([$disabled, $withoutId], $project, $issue, MemberAlertEvent::IssueAssigned));
    }

    private function user(int $id, string $email): User
    {
        $user = new User()->setEmail($email);
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
