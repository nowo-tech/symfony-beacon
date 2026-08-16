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
        $http = new MockHttpClient(static fn (): MockResponse => new MockResponse(json_encode([
            'id' => '019fea2d-507b-7890-8b33-ca488db6f696',
            'status' => 'queued',
        ], \JSON_THROW_ON_ERROR), ['http_code' => 202]));

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

    public function testTwilioCompatibleIncludesOptionalFieldsAndNormalizesDefaults(): void
    {
        $requests = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options];

            return new MockResponse(json_encode([
                'sid' => 'SMcccccccccccccccccccccccccccccccc',
                'status' => 'sent',
            ], \JSON_THROW_ON_ERROR), ['http_code' => 200]);
        });

        $provider = new SmsBridgeProvider(
            $http,
            'https://sms-bridge.example/',
            'smscli_testtoken',
            'ACbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            ' +34600000001 ',
            'device-1',
            'twilio',
        );

        $result = $provider->send(new SmsOutboundMessage(
            '+34 600111222',
            ' Hello ',
            null,
            null,
            'client-1',
            'https://example.com/status',
        ));

        parse_str((string) $requests[0][2]['body'], $formBody);

        self::assertSame('sms_bridge', $provider->getProviderId());
        self::assertSame('SMcccccccccccccccccccccccccccccccc', $result->providerMessageId);
        self::assertSame('+34600111222', $formBody['To']);
        self::assertSame('Hello', $formBody['Body']);
        self::assertSame('+34600000001', $formBody['From']);
        self::assertSame('device-1', $formBody['DeviceId']);
        self::assertSame('client-1', $formBody['ClientMessageId']);
        self::assertSame('https://example.com/status', $formBody['StatusCallback']);
    }

    public function testRejectsUnconfiguredEmptyBodyAndRemoteErrors(): void
    {
        $unconfigured = new SmsBridgeProvider(new MockHttpClient());
        $this->expectException(SmsSendException::class);
        $this->expectExceptionMessage('not configured');
        $unconfigured->send(new SmsOutboundMessage('+34600111222', 'Hi'));
    }

    public function testRejectsEmptyBody(): void
    {
        $provider = new SmsBridgeProvider(
            new MockHttpClient(),
            'https://sms-bridge.example',
            'smscli_testtoken',
            'ACbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        );

        $this->expectException(SmsSendException::class);
        $this->expectExceptionMessage('SMS body is required');
        $provider->send(new SmsOutboundMessage('+34600111222', '   '));
    }

    public function testTwilioAndNativeFailuresBubbleMeaningfulErrors(): void
    {
        $twilio = new SmsBridgeProvider(
            new MockHttpClient(static fn (): MockResponse => new MockResponse(json_encode([
                'message' => 'twilio down',
            ], \JSON_THROW_ON_ERROR), ['http_code' => 500])),
            'https://sms-bridge.example',
            'smscli_testtoken',
            'ACbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        );

        try {
            $twilio->send(new SmsOutboundMessage('+34600111222', 'Hello'));
            self::fail('Expected SmsSendException for Twilio failure');
        } catch (SmsSendException $e) {
            self::assertStringContainsString('HTTP 500', $e->getMessage());
            self::assertStringContainsString('twilio down', $e->getMessage());
        }

        $native = new SmsBridgeProvider(
            new MockHttpClient(static fn (): MockResponse => new MockResponse(json_encode([
                'error' => 'invalid device',
            ], \JSON_THROW_ON_ERROR), ['http_code' => 400])),
            'https://sms-bridge.example',
            'smscli_testtoken',
            '',
            '',
            '',
            'native',
        );

        $this->expectException(SmsSendException::class);
        $this->expectExceptionMessage('invalid device');
        $native->send(new SmsOutboundMessage('+34600111222', 'Hello'));
    }

    public function testIsConfiguredRespectsModeSpecificCredentials(): void
    {
        self::assertFalse((new SmsBridgeProvider(new MockHttpClient(), 'https://sms-bridge.example', '', 'ACbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'))->isConfigured());
        self::assertFalse((new SmsBridgeProvider(new MockHttpClient(), 'https://sms-bridge.example', 'smscli_testtoken', 'bad-sid'))->isConfigured());
        self::assertFalse((new SmsBridgeProvider(new MockHttpClient(), 'https://sms-bridge.example', 'Bearer abc', '', '', '', 'native'))->isConfigured());
        self::assertTrue((new SmsBridgeProvider(new MockHttpClient(), 'https://sms-bridge.example', 'smscli_token', '', '', '', 'native'))->isConfigured());
    }
}
