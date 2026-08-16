<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Sms;

use App\Shared\Sms\ConfiguredSmsSender;
use App\Shared\Sms\Exception\SmsSendException;
use App\Shared\Sms\NullSmsSender;
use App\Shared\Sms\Provider\SmsBridgeProvider;
use App\Shared\Sms\Provider\SmsProviderId;
use App\Shared\Sms\SmsOutboundMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ConfiguredSmsSenderAndHelpersTest extends TestCase
{
    public function testConfiguredSenderDelegatesToActiveProvider(): void
    {
        $bridge = new SmsBridgeProvider(
            new MockHttpClient(static fn (): MockResponse => new MockResponse(json_encode([
                'sid' => 'SMconfiguredsender',
                'status' => 'queued',
            ], \JSON_THROW_ON_ERROR), ['http_code' => 200])),
            'https://sms-bridge.example',
            'smscli_testtoken',
            'ACbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        );

        $sender = new ConfiguredSmsSender($bridge, new NullSmsSender(), 'sms_bridge');
        $result = $sender->send(new SmsOutboundMessage('+34600111222', 'Hello'));

        self::assertSame('SMconfiguredsender', $result->providerMessageId);
        self::assertSame('sms_bridge', $sender->getProviderId());
        self::assertTrue($sender->isConfigured());
    }

    public function testConfiguredSenderRejectsSelectedButUnconfiguredProvider(): void
    {
        $sender = new ConfiguredSmsSender(new SmsBridgeProvider(new MockHttpClient()), new NullSmsSender(), 'null');

        $this->expectException(SmsSendException::class);
        $this->expectExceptionMessage('not fully configured');
        $sender->send(new SmsOutboundMessage('+34600111222', 'Hello'));
    }

    public function testNullSenderAndProviderIdFallbacks(): void
    {
        $null = new NullSmsSender();
        self::assertFalse($null->isConfigured());
        self::assertSame('null', $null->getProviderId());
        self::assertSame('null', SmsProviderId::fromEnv('disabled')->value);
        self::assertSame('null', SmsProviderId::fromEnv('none')->value);
        self::assertSame('sms_bridge', SmsProviderId::fromEnv('sms_bridge')->value);

        try {
            $null->send(new SmsOutboundMessage('+34600111222', 'Hello'));
            self::fail('Expected disabled sender to throw');
        } catch (SmsSendException $e) {
            self::assertStringContainsString('disabled', $e->getMessage());
        }
    }
}
