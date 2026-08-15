<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Sms;

use App\Shared\Sms\ConfiguredSmsSender;
use App\Shared\Sms\Exception\SmsSendException;
use App\Shared\Sms\NullSmsSender;
use App\Shared\Sms\Provider\SmsBridgeProvider;
use App\Shared\Sms\SmsOutboundMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SmsBridgeProviderTest extends TestCase
{
    public function testTwilioCompatibleCreate(): void
    {
        $requests = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options];

            return new MockResponse(json_encode([
                'sid' => 'SMaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'status' => 'queued',
                'to' => '+34600111222',
            ], \JSON_THROW_ON_ERROR), ['http_code' => 201]);
        });

        $provider = new SmsBridgeProvider(
            $http,
            'https://sms-bridge.example',
            'smscli_testtoken',
            'ACbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            '+34600000001',
            '',
            'twilio',
        );

        self::assertTrue($provider->isConfigured());
        $result = $provider->send(new SmsOutboundMessage('+34600111222', 'Hello'));

        self::assertSame('sms_bridge', $result->providerId);
        self::assertSame('SMaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $result->providerMessageId);
        self::assertSame('queued', $result->status);
        self::assertCount(1, $requests);
        self::assertSame('POST', $requests[0][0]);
        self::assertStringContainsString('/2010-04-01/Accounts/ACbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb/Messages.json', $requests[0][1]);
    }

    public function testNativeCreate(): void
    {
        $http = new MockHttpClient(static function (): MockResponse {
            return new MockResponse(json_encode([
                'id' => '019fea2d-507b-7890-8b33-ca488db6f696',
                'status' => 'queued',
            ], \JSON_THROW_ON_ERROR), ['http_code' => 202]);
        });

        $provider = new SmsBridgeProvider(
            $http,
            'https://sms-bridge.example',
            'smscli_testtoken',
            '',
            '',
            '',
            'native',
        );

        $result = $provider->send(new SmsOutboundMessage('+34600111222', 'Hi'));
        self::assertSame('019fea2d-507b-7890-8b33-ca488db6f696', $result->providerMessageId);
    }

    public function testRejectsInvalidPhone(): void
    {
        $provider = new SmsBridgeProvider(
            new MockHttpClient(),
            'https://sms-bridge.example',
            'smscli_testtoken',
            'ACbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        );

        $this->expectException(SmsSendException::class);
        $provider->send(new SmsOutboundMessage('600111222', 'x'));
    }

    public function testConfiguredSenderSelectsNull(): void
    {
        $sender = new ConfiguredSmsSender(
            new SmsBridgeProvider(new MockHttpClient()),
            new NullSmsSender(),
            'null',
        );
        self::assertFalse($sender->isConfigured());
        self::assertSame('null', $sender->getProviderId());
    }

    public function testConfiguredSenderSelectsSmsBridge(): void
    {
        $bridge = new SmsBridgeProvider(
            new MockHttpClient(),
            'https://sms-bridge.example',
            'smscli_testtoken',
            'ACbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        );
        $sender = new ConfiguredSmsSender($bridge, new NullSmsSender(), 'sms_bridge');
        self::assertTrue($sender->isConfigured());
        self::assertSame('sms_bridge', $sender->getProviderId());
    }
}
