<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Issues\Service\EventPayloadPromoter;
use PHPUnit\Framework\TestCase;

final class EventPayloadPromoterExtraTest extends TestCase
{
    public function testExtractTagsSkipsBlankValuesAndStopsAtLimit(): void
    {
        $payload = ['tags' => ['  ' => 'ignored', 'bool_true' => true, 'bool_false' => false, 'blank' => '   ']];
        for ($i = 0; $i < 45; ++$i) {
            $payload['tags']['tag_'.$i] = 'value_'.$i;
        }

        $tags = (new EventPayloadPromoter())->extractTags($payload);

        self::assertSame(['key' => 'bool_true', 'value' => 'true'], $tags[0]);
        self::assertSame(['key' => 'bool_false', 'value' => 'false'], $tags[1]);
        self::assertCount(40, $tags);
    }

    public function testExtractRequestUrlReturnsNullWhenRequestHasNoSupportedKey(): void
    {
        $promoter = new EventPayloadPromoter();
        self::assertNull($promoter->extractRequestUrl(['request' => ['method' => 'GET']]));
        self::assertNull($promoter->extractRequestUrl(['contexts' => ['request' => ['uri' => '']]]));
    }
}
