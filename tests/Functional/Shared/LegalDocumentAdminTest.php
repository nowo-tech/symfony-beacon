<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use App\Identity\Entity\User;
use App\Shared\Legal\Entity\LegalDocument;
use App\Shared\Legal\Repository\LegalDocumentRepository;
use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class LegalDocumentAdminTest extends DatabaseWebTestCase
{
    public function testLegalEditorRequiresAdmin(): void
    {
        [$client, $user] = $this->bootWithDemoProject('legal-member@example.com');
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/admin/legal');
        self::assertResponseStatusCodeSame(403);

        $client->request(Request::METHOD_GET, '/admin/legal/notice/en');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminPublishesLocaleOverrideWithoutForkingTemplates(): void
    {
        [$client, $user] = $this->bootWithDemoProject('legal-admin@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user->setRoles(['ROLE_ADMIN']);
        $em->flush();

        $client->disableReboot();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/admin/legal');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Built-in');

        foreach (['notice', 'privacy', 'terms', 'cookies'] as $slug) {
            $client->request(Request::METHOD_GET, '/admin/legal/'.$slug.'/en');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('textarea');
        }

        foreach (['notice', 'privacy', 'terms', 'cookies'] as $slug) {
            $client->request(Request::METHOD_GET, '/admin/legal/'.$slug.'/en');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('textarea');
        }

        $crawler = $client->request(Request::METHOD_GET, '/admin/legal/notice/en');
        $reset = $crawler->selectButton('Restore built-in text')->form();
        $reset['form[_token]'] = 'not-a-token';
        $client->submit($reset);
        self::assertResponseRedirects('/admin/legal/notice/en');

        $crawler = $client->request(Request::METHOD_GET, '/admin/legal/notice/en');
        $client->submit($crawler->selectButton('Restore built-in text')->form());
        self::assertResponseRedirects('/admin/legal/notice/en');

        $crawler = $client->request(Request::METHOD_GET, '/admin/legal/notice/en');
        $client->submit($crawler->selectButton('Save legal page')->form([
            'legal_document[title]' => 'Operator notice',
            'legal_document[body]' => '<script>alert(1)</script>',
        ]));
        self::assertResponseRedirects('/admin/legal/notice/en');

        $client->request(Request::METHOD_GET, '/en/legal/notice');
        self::assertSelectorTextContains('body', 'Operator legal name');
        self::assertStringNotContainsString('alert(1)', (string) $client->getResponse()->getContent());

        $crawler = $client->request(Request::METHOD_GET, '/admin/legal/notice/en');
        $client->submit($crawler->selectButton('Save legal page')->form([
            'legal_document[title]' => 'Operator notice',
            'legal_document[body]' => '<p>Operator custom notice</p><script>alert(1)</script>',
        ]));
        self::assertResponseRedirects('/admin/legal/notice/en');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Restore built-in text');

        $client->request(Request::METHOD_GET, '/en/legal/notice');
        self::assertSelectorTextContains('body', 'Operator custom notice');
        self::assertStringNotContainsString('alert(1)', (string) $client->getResponse()->getContent());

        $crawler = $client->request(Request::METHOD_GET, '/admin/legal/notice/en');
        $client->submit($crawler->selectButton('Save legal page')->form([
            'legal_document[title]' => 'Operator notice',
            'legal_document[body]' => '<p>Kept</p><iframe src="https://www.youtube.com/embed/x" srcdoc="<script>alert(9)</script>"></iframe><img src=x onerror=alert(9)>',
        ]));
        self::assertResponseRedirects('/admin/legal/notice/en');
        $crawler = $client->request(Request::METHOD_GET, '/en/legal/notice');
        $article = strtolower($crawler->filter('article.legal-doc')->html());
        self::assertStringContainsString('kept', $article);
        self::assertStringNotContainsString('<iframe', $article);
        self::assertStringNotContainsString('onerror', $article);
        self::assertStringNotContainsString('alert(9)', $article);

        $em->clear();
        $stored = self::getContainer()->get(LegalDocumentRepository::class)->findOneBySlugAndLocale('notice', 'en');
        self::assertInstanceOf(LegalDocument::class, $stored);
        self::assertSame('Operator notice', $stored->getTitle());
        self::assertStringContainsString('Kept', $stored->getBody());
        self::assertStringNotContainsString('iframe', strtolower($stored->getBody()));
        self::assertStringNotContainsString('onerror', strtolower($stored->getBody()));
        self::assertStringNotContainsString('script', strtolower($stored->getBody()));
        self::assertInstanceOf(User::class, $stored->getCreatedBy());
        self::assertInstanceOf(User::class, $stored->getUpdatedBy());
        self::assertNotNull($stored->getId());

        $client->request(Request::METHOD_GET, '/admin/legal');
        self::assertSelectorTextContains('body', 'Custom');

        $crawler = $client->request(Request::METHOD_GET, '/admin/legal/notice/en');
        $client->submit($crawler->selectButton('Save legal page')->form([
            'legal_document[title]' => 'Operator notice',
            'legal_document[body]' => '<script>alert(2)</script>',
        ]));
        self::assertResponseRedirects('/admin/legal/notice/en');

        $client->request(Request::METHOD_GET, '/en/legal/notice');
        self::assertSelectorTextContains('body', 'Kept');

        $crawler = $client->request(Request::METHOD_GET, '/admin/legal/notice/en');
        $client->submit($crawler->selectButton('Restore built-in text')->form());
        self::assertResponseRedirects('/admin/legal/notice/en');

        $client->request(Request::METHOD_GET, '/en/legal/notice');
        self::assertSelectorTextContains('body', 'Operator legal name');
        self::assertStringNotContainsString('Operator custom notice', (string) $client->getResponse()->getContent());
        self::assertNull(self::getContainer()->get(LegalDocumentRepository::class)->findOneBySlugAndLocale('notice', 'en'));
    }
}
