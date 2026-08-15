<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Issues\Service\EventPayloadPromoter;
use PHPUnit\Framework\TestCase;

final class EventPayloadPromoterTest extends TestCase
{
    private EventPayloadPromoter $promoter;

    protected function setUp(): void
    {
        $this->promoter = new EventPayloadPromoter();
    }

    public function testExtractTagsFromPayloadTags(): void
    {
        $tags = $this->promoter->extractTags([
            'tags' => [
                'env' => 'prod',
                'feature' => 'checkout',
                'empty' => '',
                'nested' => ['x' => 1],
            ],
        ]);

        self::assertSame([
            ['key' => 'env', 'value' => 'prod'],
            ['key' => 'feature', 'value' => 'checkout'],
        ], $tags);
    }

    public function testExtractRequestUrlFromRequestAndContexts(): void
    {
        self::assertSame(
            'https://example.com/a',
            $this->promoter->extractRequestUrl(['request' => ['url' => 'https://example.com/a']]),
        );
        self::assertSame(
            'https://example.com/b',
            $this->promoter->extractRequestUrl([
                'contexts' => ['request' => ['url' => 'https://example.com/b']],
            ]),
        );
        self::assertNull($this->promoter->extractRequestUrl([]));
    }
}
