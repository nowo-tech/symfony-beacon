<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Entity;

use App\Issues\Entity\Event;
use App\Issues\Entity\EventTag;
use App\Issues\Entity\Issue;
use App\Project\Entity\Project;
use PHPUnit\Framework\TestCase;

final class EventTagTest extends TestCase
{
    public function testTracksRelationsAndTagFields(): void
    {
        $event = new Event();
        $issue = new Issue();
        $project = new Project();
        $tag = new EventTag()
            ->setEvent($event)
            ->setIssue($issue)
            ->setProject($project)
            ->setTagKey('environment')
            ->setTagValue('production');

        self::assertSame($event, $tag->getEvent());
        self::assertSame($issue, $tag->getIssue());
        self::assertSame($project, $tag->getProject());
        self::assertSame('environment', $tag->getTagKey());
        self::assertSame('production', $tag->getTagValue());
    }
}
