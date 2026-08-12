<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications;

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
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class MemberAlertPreferenceEvaluatorTest extends TestCase
{
    public function testDefaultsAllowNotificationWhenNoRows(): void
    {
        $user = $this->user(true);
        $project = new Project();
        $issue = new Issue();
        $issue->setProject($project);

        $evaluator = $this->evaluator(
            projectPref: null,
            accountEvent: null,
            projectEvent: null,
            mentioned: false,
        );

        self::assertTrue($evaluator->shouldNotify($user, $project, $issue, MemberAlertEvent::IssueNew));
    }

    public function testMasterOffBlocksAll(): void
    {
        $user = $this->user(false);
        $project = new Project();
        $issue = new Issue();

        $evaluator = $this->evaluator(null, null, null, false);

        self::assertFalse($evaluator->shouldNotify($user, $project, $issue, MemberAlertEvent::IssueResolved));
    }

    public function testProjectDisabledBlocks(): void
    {
        $user = $this->user(true);
        $project = new Project();
        $issue = new Issue();
        $pref = new MemberProjectAlertPreference();
        $pref->setEnabled(false);

        $evaluator = $this->evaluator($pref, null, null, false);

        self::assertFalse($evaluator->shouldNotify($user, $project, $issue, MemberAlertEvent::IssueNew));
    }

    public function testAccountEventDisabledBlocks(): void
    {
        $user = $this->user(true);
        $project = new Project();
        $issue = new Issue();
        $account = new MemberAccountAlertEvent();
        $account->setEnabled(false);
        $account->setScope(MemberAlertScope::All);

        $evaluator = $this->evaluator(null, $account, null, false);

        self::assertFalse($evaluator->shouldNotify($user, $project, $issue, MemberAlertEvent::IssueAssigned));
    }

    public function testProjectOverrideWinsOverAccount(): void
    {
        $user = $this->user(true);
        $project = new Project();
        $issue = new Issue();
        $account = new MemberAccountAlertEvent();
        $account->setEnabled(false);
        $account->setScope(MemberAlertScope::All);
        $override = new MemberProjectAlertEvent();
        $override->setEnabled(true);
        $override->setScope(MemberAlertScope::All);

        $evaluator = $this->evaluator(null, $account, $override, false);

        self::assertTrue($evaluator->shouldNotify($user, $project, $issue, MemberAlertEvent::IssueCommented));
    }

    public function testInvolvedScopeRequiresAssigneeOrMention(): void
    {
        $user = $this->user(true, 10);
        $other = $this->user(true, 20);
        $project = new Project();
        $issue = new Issue();
        $issue->setAssignee($other);

        $account = new MemberAccountAlertEvent();
        $account->setEnabled(true);
        $account->setScope(MemberAlertScope::Involved);

        $notInvolved = $this->evaluator(null, $account, null, false);
        self::assertFalse($notInvolved->shouldNotify($user, $project, $issue, MemberAlertEvent::IssueResolved));

        $mentioned = $this->evaluator(null, $account, null, true);
        self::assertTrue($mentioned->shouldNotify($user, $project, $issue, MemberAlertEvent::IssueResolved));

        $issue->setAssignee($user);
        $asAssignee = $this->evaluator(null, $account, null, false);
        self::assertTrue($asAssignee->shouldNotify($user, $project, $issue, MemberAlertEvent::IssueResolved));
    }

    private function user(bool $memberAlertsEnabled, ?int $id = null): User
    {
        $user = new User();
        $user->setEmail(uniqid('eval-', true).'@example.com');
        $user->setPassword('x');
        $user->setMemberAlertsEnabled($memberAlertsEnabled);
        if (null !== $id) {
            $ref = new \ReflectionProperty(User::class, 'id');
            $ref->setValue($user, $id);
        }

        return $user;
    }

    private function evaluator(
        ?MemberProjectAlertPreference $projectPref,
        ?MemberAccountAlertEvent $accountEvent,
        ?MemberProjectAlertEvent $projectEvent,
        bool $mentioned,
    ): MemberAlertPreferenceEvaluator {
        /** @var Stub&MemberProjectAlertPreferenceRepository $projectPrefRepo */
        $projectPrefRepo = $this->createStub(MemberProjectAlertPreferenceRepository::class);
        $projectPrefRepo->method('findOneByUserAndProject')->willReturn($projectPref);

        /** @var Stub&MemberAccountAlertEventRepository $accountRepo */
        $accountRepo = $this->createStub(MemberAccountAlertEventRepository::class);
        $accountRepo->method('findOneByUserAndEvent')->willReturn($accountEvent);

        /** @var Stub&MemberProjectAlertEventRepository $projectEventRepo */
        $projectEventRepo = $this->createStub(MemberProjectAlertEventRepository::class);
        $projectEventRepo->method('findOneByUserProjectAndEvent')->willReturn($projectEvent);

        /** @var Stub&IssueMentionRepository $mentionRepo */
        $mentionRepo = $this->createStub(IssueMentionRepository::class);
        $mentionRepo->method('isUserMentionedOnIssue')->willReturn($mentioned);

        return new MemberAlertPreferenceEvaluator(
            $projectPrefRepo,
            $accountRepo,
            $projectEventRepo,
            $mentionRepo,
        );
    }
}
