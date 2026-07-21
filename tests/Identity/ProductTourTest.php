<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\Service\ProductTourStepsBuilder;
use App\Identity\Tour\ProductTourContext;
use App\Identity\Tour\ProductTourPage;
use App\Shared\ProjectRole;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Tests\Shared\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class ProductTourTest extends DatabaseWebTestCase
{
    public function testMarkSeenEndpointRequiresCsrf(): void
    {
        [$client, $user] = $this->bootWithDemoProject('tour-csrf@example.com');
        $this->login($client, $user);

        $client->request(
            Request::METHOD_POST,
            '/account/product-tour/seen',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['seen' => true, 'page' => 'dashboard'], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(403);
    }

    public function testMarkSeenPageStopsAutoStartOnDashboardOnly(): void
    {
        [$client, $user] = $this->bootWithDemoProject('tour-mark@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $settings = self::getContainer()->get(InstanceSettingsRepository::class)->getOrCreate();
        $settings->markSetupCompleted();
        self::getContainer()->get(InstanceSettingsRepository::class)->save($settings);

        $this->login($client, $user);
        $crawler = $client->request(Request::METHOD_GET, '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertSame(
            'true',
            $crawler->filter('[data-controller~="product-tour"]')->attr('data-product-tour-auto-start-value'),
        );
        self::assertSame(
            'dashboard',
            $crawler->filter('[data-product-tour-page-value]')->attr('data-product-tour-page-value'),
        );

        $token = (string) $crawler->filter('[data-product-tour-mark-token-value]')->attr('data-product-tour-mark-token-value');
        $client->request(
            Request::METHOD_POST,
            '/account/product-tour/seen',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $token,
            ],
            content: json_encode(['seen' => true, 'page' => 'dashboard'], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseIsSuccessful();

        $em->clear();
        $reloaded = $em->find($user::class, $user->getId());
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->hasSeenTourPage('dashboard'));
        self::assertFalse($reloaded->isProductTourSeen());
        self::assertFalse($reloaded->hasSeenTourPage('admin'));

        $crawler = $client->request(Request::METHOD_GET, '/dashboard');
        self::assertSame(
            'false',
            $crawler->filter('[data-controller~="product-tour"]')->attr('data-product-tour-auto-start-value'),
        );
    }

    public function testDashboardTourOmitsAdminStepForNonAdmin(): void
    {
        $builder = self::getContainer()->get(ProductTourStepsBuilder::class);
        $steps = $builder->build(new ProductTourContext(
            page: ProductTourPage::Dashboard,
            isInstanceAdmin: false,
            canCreateProject: true,
        ));
        $selectors = array_values(array_filter(array_map(
            static fn (array $step): ?string => $step['element'] ?? null,
            $steps,
        )));
        self::assertNotContains('[data-tour="admin-link"]', $selectors);
        self::assertContains('[data-tour="new-project"]', $selectors);
    }

    public function testProjectTourVariesByRole(): void
    {
        $builder = self::getContainer()->get(ProductTourStepsBuilder::class);

        $viewer = $builder->build(new ProductTourContext(
            page: ProductTourPage::ProjectIssues,
            isInstanceAdmin: false,
            canCreateProject: true,
            projectRole: ProjectRole::Viewer,
        ));
        $owner = $builder->build(new ProductTourContext(
            page: ProductTourPage::ProjectIssues,
            isInstanceAdmin: false,
            canCreateProject: true,
            projectRole: ProjectRole::Owner,
        ));

        $viewerSelectors = array_values(array_filter(array_map(
            static fn (array $step): ?string => $step['element'] ?? null,
            $viewer,
        )));
        $ownerSelectors = array_values(array_filter(array_map(
            static fn (array $step): ?string => $step['element'] ?? null,
            $owner,
        )));

        self::assertNotContains('[data-tour="issue-saved-views"]', $viewerSelectors);
        self::assertContains('[data-tour="issue-saved-views"]', $ownerSelectors);
        self::assertContains('[data-tour="project-settings"]', $ownerSelectors);
        self::assertNotContains('[data-tour="project-settings"]', $viewerSelectors);
    }

    public function testDisplayTourMultiselectCanDisableAllTours(): void
    {
        [$client, $user] = $this->bootWithDemoProject('tour-prefs@example.com');
        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/account/display');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save display settings')->form();
        foreach ($form['user_preferences']['productTourEnabledPages'] as $checkbox) {
            $checkbox->untick();
        }
        $client->submit($form);
        self::assertResponseRedirects('/account/display');

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $reloaded = $em->find($user::class, $user->getId());
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isProductTourSeen());
        self::assertTrue($reloaded->hasSeenTourPage('dashboard'));
        self::assertTrue($reloaded->hasSeenTourPage('project_issues'));
        self::assertTrue($reloaded->hasSeenTourPage('admin'));
        self::assertSame([], $reloaded->getEnabledProductTourPages());
    }

    public function testEnablingSelectedToursReEnablesAutoStart(): void
    {
        [$client, $user] = $this->bootWithDemoProject('tour-uncheck@example.com');
        $user->markProductTourSeen();
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->login($client, $user);
        $crawler = $client->request(Request::METHOD_GET, '/account/display');
        $form = $crawler->selectButton('Save display settings')->form();
        foreach ($form['user_preferences']['productTourEnabledPages'] as $checkbox) {
            $checkbox->untick();
        }
        $form['user_preferences']['productTourEnabledPages'][0]->tick(); // dashboard
        $form['user_preferences']['productTourEnabledPages'][1]->tick(); // project_issues
        $client->submit($form);
        self::assertResponseRedirects('/account/display');

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $reloaded = $em->find($user::class, $user->getId());
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isProductTourSeen());
        self::assertFalse($reloaded->hasSeenTourPage('dashboard'));
        self::assertFalse($reloaded->hasSeenTourPage('project_issues'));
        self::assertTrue($reloaded->hasSeenTourPage('admin'));
        self::assertSame(['dashboard', 'project_issues'], $reloaded->getEnabledProductTourPages());
    }

    public function testReplayClearsSeenAndForcesTourQuery(): void
    {
        [$client, $user] = $this->bootWithDemoProject('tour-replay@example.com');
        $user->markProductTourSeen();
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->login($client, $user);
        $crawler = $client->request(Request::METHOD_GET, '/account/display');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[action$="/account/product-tour/replay"]')->form();
        $client->submit($form);
        self::assertResponseRedirects('/dashboard?tour=1');

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSame(
            'true',
            $client->getCrawler()->filter('[data-controller~="product-tour"]')->attr('data-product-tour-force-value'),
        );
    }
}
