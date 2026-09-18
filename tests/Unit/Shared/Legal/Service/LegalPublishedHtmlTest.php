<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Legal\Service;

use App\Shared\Legal\Service\LegalPublishedHtml;
use Nowo\Ckeditor5EditorBundle\Security\Ckeditor5HtmlSanitizerInterface;
use PHPUnit\Framework\TestCase;

final class LegalPublishedHtmlTest extends TestCase
{
    public function testStripsIframesEventHandlersAndScriptUrlsTheKitLeaves(): void
    {
        $html = '<p>Kept</p>'
            .'<iframe src="https://www.youtube.com/embed/x" srcdoc="<script>alert(1)</script>"></iframe>'
            .'<img src=x onerror=alert(2)>'
            .'<a href=javascript:alert(3)>x</a>'
            .'<a href="javascript:alert(4)">y</a>';

        $clean = LegalPublishedHtml::stripHazards($html);

        self::assertStringContainsString('Kept', $clean);
        self::assertStringNotContainsString('iframe', strtolower($clean));
        self::assertStringNotContainsString('srcdoc', strtolower($clean));
        self::assertStringNotContainsString('onerror', strtolower($clean));
        self::assertStringNotContainsString('javascript:', strtolower($clean));
        self::assertStringNotContainsString('alert', strtolower($clean));
    }

    public function testSanitizeRunsTheKitFilterFirst(): void
    {
        $kit = $this->createStub(Ckeditor5HtmlSanitizerInterface::class);
        $kit->method('sanitize')->willReturn('<p>ok</p><iframe src="https://www.youtube.com/embed/x"></iframe>');

        $clean = new LegalPublishedHtml($kit)->sanitize('<p>ok</p><script>nope</script>');

        self::assertSame('<p>ok</p>', $clean);
    }
}
