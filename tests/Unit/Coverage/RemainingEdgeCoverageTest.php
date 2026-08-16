<?php

declare(strict_types=1);

namespace App\Tests\Unit\Coverage;

use App\Ingest\Otlp\Service\OtlpLogsMapper;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueLevel;
use App\Issues\Enum\IssueStatus;
use App\Issues\Export\AiIssueExportFormatter;
use App\Issues\Form\ProjectMemberAutocompleteField;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Formatter\DiscordChannelFormatter;
use App\Notifications\Message\DeliverNotificationMessage;
use App\Notifications\NotificationCategories;
use App\Notifications\Realtime\MemberIssueRealtimeNotifierInterface;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Repository\NotificationDigestBufferRepository;
use App\Notifications\Service\NotificationCircuitBreaker;
use App\Notifications\Service\NotificationDispatcher;
use App\Notifications\Service\NotificationPayloadBuilder;
use App\Notifications\Service\QuietHoursEvaluator;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectShareLink;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectShareGrantStore;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RemainingEdgeCoverageTest extends TestCase
{
    public function testAiIssueExportFormatterKeepsNullMessageForEventPayloadWithoutStringMessage(): void
    {
        $project = new Project()->setName('Beacon')->setSlug('beacon');
        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint('fp-edge');
        $issue->setTitle('Edge');
        $issue->setCulprit('App\\Edge');
        $issue->setLevel(IssueLevel::Error);
        $issue->setStatus(IssueStatus::Unresolved);

        $event = new Event();
        $event->setIssue($issue);
        $event->setPayload(['message' => ['not-a-string']]);

        $data = new AiIssueExportFormatter()->buildCanonical($project, $issue, $event, 'https://beacon.test/issues/edge');

        self::assertNull($data['event']['message']);
    }

    public function testDiscordEmbedDropsMissingUrlKey(): void
    {
        $method = new ReflectionMethod(DiscordChannelFormatter::class, 'discordEmbed');
        $embed = $method->invoke(new DiscordChannelFormatter(), ['event' => 'issue.new'], 'Boom');

        self::assertArrayNotHasKey('url', $embed);
    }

    public function testProjectMemberAutocompleteDefaultsInvalidProjectIdAndSkipsBlankSearchTerm(): void
    {
        $type = new ProjectMemberAutocompleteField($this->formOptionsMerger(), new FormTypeMap());
        $resolver = new OptionsResolver();
        $resolver->setDefined('extra_options');
        $type->configureOptions($resolver);
        $options = $resolver->resolve([
            'extra_options' => ['project_id' => 'not-numeric'],
            'max_results' => 7,
        ]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('innerJoin')->willReturnSelf();
        $qb->expects(self::once())->method('andWhere')->with('membership.project = :projectId')->willReturnSelf();
        $qb->expects(self::once())->method('setParameter')->with('projectId', 0)->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('addOrderBy')->willReturnSelf();
        $qb->expects(self::once())->method('setMaxResults')->with(7)->willReturnSelf();

        $filter = $options['filter_query'];
        $filter($qb, '   ', $this->createStub(EntityRepository::class));
    }

    public function testOtlpLogsMapperExtractBodyReturnsRawString(): void
    {
        $method = new ReflectionMethod(OtlpLogsMapper::class, 'extractBody');

        self::assertSame('raw body', $method->invoke(new OtlpLogsMapper(), 'raw body'));
    }

    public function testNotificationDispatcherFallsBackUnknownLevelsToError(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('Webhook');
        $destination->setEnabled(true);
        $destination->setCategories(['error']);
        new ReflectionProperty(NotificationDestination::class, 'id')->setValue($destination, 1);

        $repo = $this->createStub(NotificationDestinationRepository::class);
        $repo->method('findEnabledByProject')->willReturn([$destination]);
        $digest = $this->createStub(NotificationDigestBufferRepository::class);
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://beacon.test/i/1');
        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn(InstanceSettings::defaults());
        $messages = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $message) use (&$messages): Envelope {
            $messages[] = $message;

            return new Envelope($message);
        });
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush');

        $dispatcher = new NotificationDispatcher(
            $repo,
            $digest,
            new NotificationPayloadBuilder($urls),
            new QuietHoursEvaluator(),
            new NotificationCircuitBreaker(new InstanceOpsDefaults($settingsRepo)),
            $bus,
            $em,
            $this->createStub(MemberIssueRealtimeNotifierInterface::class),
        );

        $method = new ReflectionMethod(NotificationDispatcher::class, 'dispatchIssuePayload');
        $method->invoke($dispatcher, $project, 'unknown-level', [
            'event' => 'issue.new',
            'category' => NotificationCategories::ISSUE_RESOLVED,
            'summary' => 'Boom',
        ]);

        self::assertInstanceOf(DeliverNotificationMessage::class, $messages[0]);
        self::assertSame('error', $messages[0]->payload['category']);
    }

    public function testProjectShareGrantStoreNormalizesNonStringIssueToNull(): void
    {
        $request = Request::create('/');
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $project = new Project()->setName('Beacon')->setSlug('beacon');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        $link = new ProjectShareLink()->setProject($project)->setTokenHash('hash');
        $repo = $this->createStub(ProjectShareLinkRepository::class);
        $repo->method('findOneByUuid')->willReturn($link);

        $session->set(ProjectShareGrantStore::SHARE_ACCESS_SESSION_KEY, [
            $project->getUuid() => ['expires' => time() + 60, 'share' => $link->getUuid(), 'issue' => false],
        ]);

        self::assertNull(new ProjectShareGrantStore($stack, $repo)->getActiveShareEntry($project)['issue']);
    }

    private function formOptionsMerger(): FormOptionsMerger
    {
        return new FormOptionsMerger([
            'beacon' => [
                'translation_domain' => 'form',
                'auto_placeholder' => true,
                'auto_help' => true,
                'defaults' => [
                    'attr' => ['class' => 'input'],
                    'row_attr' => ['class' => 'row'],
                ],
                'field_types' => [
                    'text' => [],
                    'textarea' => [],
                    'checkbox' => [],
                    'choice' => [],
                    'password' => [],
                ],
            ],
        ], 'beacon', new ConstraintDefinitionFactory());
    }
}
