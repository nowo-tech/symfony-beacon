<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Tests\Support\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class QrLoginImageFunctionalTest extends DatabaseWebTestCase
{
    public function testQrShowPageIncludesImageDataUri(): void
    {
        [$client] = $this->bootWithDemoProject('qr-png@example.com');

        $client->request(Request::METHOD_GET, '/login/qr');
        self::assertResponseRedirects();
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent() ?: '';
        self::assertMatchesRegularExpression(
            '#src="data:image/(png|svg\+xml);base64,#',
            $content,
            'QR show page should render an Endroid image data URI',
        );
        self::assertStringContainsString('nowo-auth-kit__panel--qr', $content);
        self::assertStringContainsString('data-controller="qr-login"', $content);
        self::assertMatchesRegularExpression(
            '#data-qr-login-target="countdown">\d+:\d{2}</time>#',
            $content,
            'Countdown <time> should include an initial mm:ss value',
        );
    }
}
