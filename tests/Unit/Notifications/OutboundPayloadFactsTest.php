<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications;

use App\Notifications\Formatter\OutboundPayloadFacts;
use PHPUnit\Framework\TestCase;

final class OutboundPayloadFactsTest extends TestCase
{
    public function testFactFieldsFromPayload(): void
    {
        $fields = OutboundPayloadFacts::factFields([
            'project' => ['name' => 'Acme'],
            'issue' => [
                'level' => 'error',
                'title' => 'Boom',
                'culprit' => 'App\\Fail::run',
            ],
            'test' => true,
        ]);

        self::assertSame(
            [
                ['title' => 'Project', 'value' => 'Acme', 'short' => true],
                ['title' => 'Level', 'value' => 'error', 'short' => true],
                ['title' => 'Issue', 'value' => 'Boom', 'short' => false],
                ['title' => 'Culprit', 'value' => 'App\\Fail::run', 'short' => false],
                ['title' => 'Sample', 'value' => 'yes', 'short' => true],
            ],
            $fields,
        );
    }

    public function testFactFieldsSkipsEmptyCulpritAndNonScalars(): void
    {
        self::assertSame([], OutboundPayloadFacts::factFields([
            'project' => ['name' => ['nested']],
            'issue' => [
                'level' => ['x'],
                'title' => null,
                'culprit' => '',
            ],
        ]));
    }

    public function testDiscordFieldsMapInlineFromShort(): void
    {
        $discord = OutboundPayloadFacts::discordFields([
            'project' => ['name' => 'Acme'],
            'issue' => ['title' => 'Boom'],
        ]);

        self::assertSame(
            [
                ['name' => 'Project', 'value' => 'Acme', 'inline' => true],
                ['name' => 'Issue', 'value' => 'Boom', 'inline' => false],
            ],
            $discord,
        );
    }

    public function testPlainTextBodySkipsSampleAndAppendsUrl(): void
    {
        $body = OutboundPayloadFacts::plainTextBody([
            'project' => ['name' => 'Acme'],
            'test' => true,
            'url' => 'https://beacon.test/i/1',
        ], 'Summary line');

        self::assertSame(
            "Summary line\nProject: Acme\n\nhttps://beacon.test/i/1",
            $body,
        );
    }
}
