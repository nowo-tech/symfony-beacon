<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications;

use App\Notifications\Service\ActionTokenConsumer;
use App\Notifications\Service\ActionTokenConsumeResult;
use App\Notifications\Service\InteractionActionToken;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class ActionTokenConsumerTest extends TestCase
{
    public function testInvalidPayloadWithoutSignature(): void
    {
        $consumer = new ActionTokenConsumer(new InteractionActionToken(), new ArrayAdapter());

        self::assertSame(ActionTokenConsumeResult::Invalid, $consumer->consumeOnce(['a' => 'resolve']));
    }

    public function testConsumesOnceThenAlreadyUsed(): void
    {
        $token = new InteractionActionToken();
        $payload = $token->issueResolveToken('secret', 'd', 'p', 'i', 1_700_000_000, 3600);
        $consumer = new ActionTokenConsumer($token, new ArrayAdapter());

        self::assertSame(ActionTokenConsumeResult::Consumed, $consumer->consumeOnce($payload));
        self::assertSame(ActionTokenConsumeResult::AlreadyUsed, $consumer->consumeOnce($payload));
    }

    public function testDistinctSignaturesAreIndependent(): void
    {
        $token = new InteractionActionToken();
        $first = $token->issueResolveToken('secret', 'd', 'p', 'i', 1_700_000_000, 3600);
        $second = $token->issueResolveToken('secret', 'd', 'p', 'i', 1_700_000_000, 3600);
        $consumer = new ActionTokenConsumer($token, new ArrayAdapter());

        self::assertSame(ActionTokenConsumeResult::Consumed, $consumer->consumeOnce($first));
        self::assertSame(ActionTokenConsumeResult::Consumed, $consumer->consumeOnce($second));
    }
}
