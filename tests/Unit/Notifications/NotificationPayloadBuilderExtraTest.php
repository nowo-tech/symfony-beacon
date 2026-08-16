<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications;

use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Service\NotificationPayloadBuilder;
use App\Project\Entity\Project;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class NotificationPayloadBuilderExtraTest extends TestCase
{
    public function testForTestUsesAllChannelLabels(): void
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://beacon.test/project_settings/demo');
        $builder = new NotificationPayloadBuilder($urls);
        $project = (new Project())->setName('Acme')->setSlug('acme');

        self::assertStringContainsString('Microsoft Teams', $builder->forTest($project, 'Ops', NotificationDestinationType::Teams)['summary']);
        self::assertStringContainsString('Telegram', $builder->forTest($project, 'Ops', NotificationDestinationType::Telegram)['summary']);
        self::assertStringContainsString('Email', $builder->forTest($project, 'Ops', NotificationDestinationType::Email)['summary']);
        self::assertStringContainsString('HTTP webhook', $builder->forTest($project, 'Ops', NotificationDestinationType::Http)['summary']);
        self::assertStringContainsString('destination', $builder->forTest($project, 'Ops', null)['summary']);
    }
}
