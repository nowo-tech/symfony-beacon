<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications;

use App\Identity\Entity\User;
use App\Identity\Form\MemberProjectAlertPreferencesType;
use App\Notifications\Entity\MemberAccountAlertEvent;
use App\Notifications\Entity\MemberProjectAlertEvent;
use App\Notifications\Entity\MemberProjectAlertPreference;
use App\Notifications\Entity\PushSubscription;
use App\Notifications\Enum\MemberAlertEvent;
use App\Notifications\Enum\MemberAlertScope;
use App\Notifications\Message\DeliverWebPushForProjectMessage;
use App\Project\Entity\Project;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MemberAlertEnumAndEntityTest extends TestCase
{
    public function testScopeFromMixed(): void
    {
        self::assertSame(MemberAlertScope::Involved, MemberAlertScope::fromMixed(MemberAlertScope::Involved));
        self::assertSame(MemberAlertScope::Involved, MemberAlertScope::fromMixed(' involved '));
        self::assertSame(MemberAlertScope::Involved, MemberAlertScope::fromMixed('INVOLVED'));
        self::assertSame(MemberAlertScope::All, MemberAlertScope::fromMixed('all'));
        self::assertSame(MemberAlertScope::All, MemberAlertScope::fromMixed(null));
        self::assertSame(MemberAlertScope::All, MemberAlertScope::fromMixed(123));
    }

    public function testEventFormMappingAndScopeFromFormRow(): void
    {
        self::assertSame('preferences.member_alerts.event.issue.new', MemberAlertEvent::IssueNew->translationKey());
        self::assertSame('issue_new', MemberAlertEvent::IssueNew->formKey());
        self::assertSame(MemberAlertEvent::IssueNew, MemberAlertEvent::tryFromFormKey('issue_new'));

        self::assertSame(
            ['enabled' => false, 'involved' => true],
            MemberAlertEvent::toFormEventRow(['enabled' => false, 'scope' => 'involved']),
        );
        self::assertSame(
            ['enabled' => true, 'scope' => 'involved'],
            MemberAlertEvent::fromFormEventRow(['enabled' => true, 'scope' => 'involved']),
        );
        self::assertSame(
            ['enabled' => true, 'scope' => 'all'],
            MemberAlertEvent::fromFormEventRow(['involved' => false]),
        );

        $mapped = MemberAlertEvent::mapEventsToFormKeys([
            'issue.new' => ['enabled' => true, 'scope' => 'all'],
            'custom.event' => ['enabled' => false, 'scope' => 'involved'],
        ]);
        self::assertArrayHasKey('issue_new', $mapped);
        self::assertArrayHasKey('custom_event', $mapped);

        $fromForm = MemberAlertEvent::mapEventsFromFormKeys([
            'issue_assigned' => ['enabled' => false, 'involved' => true],
            'weird_key' => 'not-array',
        ]);
        self::assertSame('involved', $fromForm['issue.assigned']['scope']);
        self::assertTrue($fromForm['weird.key']['enabled']);
    }

    public function testEntityAccessorsRoundTrip(): void
    {
        $user = new User();
        $user->setEmail('entity@example.com');
        $user->setPassword('x');
        $project = new Project();
        $project->setName('Acme');
        $project->setSlug('acme');

        $pref = new MemberProjectAlertPreference();
        self::assertNull($pref->getId());
        self::assertNull($pref->getUser());
        self::assertNull($pref->getProject());
        $pref->setUser($user)->setProject($project)->setEnabled(false);
        self::assertSame($user, $pref->getUser());
        self::assertSame($project, $pref->getProject());
        self::assertFalse($pref->isEnabled());
        self::assertLessThanOrEqual(new DateTimeImmutable(), $pref->getCreatedAt());
        self::assertLessThanOrEqual(new DateTimeImmutable(), $pref->getUpdatedAt());

        $account = new MemberAccountAlertEvent();
        self::assertNull($account->getId());
        self::assertNull($account->getUser());
        $account->setUser($user)->setEvent(MemberAlertEvent::IssueResolved)->setEnabled(false)->setScope(MemberAlertScope::Involved);
        self::assertSame(MemberAlertEvent::IssueResolved, $account->getEvent());
        self::assertFalse($account->isEnabled());
        self::assertSame(MemberAlertScope::Involved, $account->getScope());

        $override = new MemberProjectAlertEvent();
        self::assertNull($override->getId());
        self::assertNull($override->getUser());
        self::assertNull($override->getProject());
        $override->setUser($user)->setProject($project)->setEvent(MemberAlertEvent::IssueCommented)
            ->setEnabled(true)->setScope(MemberAlertScope::All);
        self::assertSame(MemberAlertEvent::IssueCommented, $override->getEvent());
        self::assertSame(MemberAlertEvent::IssueCommented, $override->getEvent());
        self::assertTrue($override->isEnabled());
        self::assertSame(MemberAlertScope::All, $override->getScope());
    }

    public function testFormNameAndPushMessageConstruction(): void
    {
        $project = new Project();
        $project->setName('Acme');
        $project->setSlug('acme');
        self::assertSame(
            MemberProjectAlertPreferencesType::formNameForUuid($project->getUuid()),
            MemberProjectAlertPreferencesType::formNameForProject($project),
        );

        $message = new DeliverWebPushForProjectMessage(42, ['event' => 'issue.new'], [1, 2]);
        self::assertSame(42, $message->projectId);
        self::assertSame([1, 2], $message->eligibleUserIds);
        self::assertSame('issue.new', $message->payload['event']);
    }

    public function testPushSubscriptionAccessors(): void
    {
        $user = new User();
        $user->setEmail('push-entity@example.com');
        $user->setPassword('x');
        $sub = new PushSubscription($user);
        self::assertNull($sub->getId());
        self::assertSame($user, $sub->getUser());
        $sub->setSubscription('https://fcm.googleapis.com/fcm/send/x', 'p', 'a', 'aesgcm', 'UA');
        self::assertSame('https://fcm.googleapis.com/fcm/send/x', $sub->getEndpoint());
        self::assertSame(hash('sha256', 'https://fcm.googleapis.com/fcm/send/x'), $sub->getEndpointHash());
        self::assertSame('p', $sub->getP256dh());
        self::assertSame('a', $sub->getAuthToken());
        self::assertSame('aesgcm', $sub->getContentEncoding());
        self::assertSame('UA', $sub->getUserAgent());
        self::assertLessThanOrEqual(new DateTimeImmutable(), $sub->getCreatedAt());
        self::assertLessThanOrEqual(new DateTimeImmutable(), $sub->getUpdatedAt());
        $sub->setEndpoint('cipher')->setP256dh('c1')->setAuthToken('c2');
        self::assertSame('cipher', $sub->getEndpoint());
        self::assertSame('c1', $sub->getP256dh());
        self::assertSame('c2', $sub->getAuthToken());
        $before = $sub->getUpdatedAt();
        $sub->touch();
        self::assertGreaterThanOrEqual($before, $sub->getUpdatedAt());
    }
}
