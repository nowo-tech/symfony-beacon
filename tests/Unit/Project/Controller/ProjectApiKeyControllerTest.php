<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Controller;

use App\Project\Controller\ProjectApiKeyController;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Service\HumanFriendlyTokenGenerator;
use App\Project\Service\ProjectApiKeyFactory;
use App\Identity\Service\UserActionRecorder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProjectApiKeyControllerTest extends TestCase
{
    public function testAssertKeyBelongsToProjectAndSettingsBaseUrl(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $controller = new ProjectApiKeyController(
            $em,
            new ProjectApiKeyFactory($em),
            new HumanFriendlyTokenGenerator(),
            new UserActionRecorder($em, new RequestStack()),
        );

        $project = (new Project())->setName('Acme')->setSlug('acme');
        (new ReflectionProperty(Project::class, 'id'))->setValue($project, 5);
        $other = (new Project())->setName('Other')->setSlug('other');
        (new ReflectionProperty(Project::class, 'id'))->setValue($other, 6);

        $key = new ProjectApiKey();
        $key->setProject($project);

        $assert = new ReflectionMethod(ProjectApiKeyController::class, 'assertKeyBelongsToProject');
        $assert->invoke($controller, $key, $project);

        $baseUrl = new ReflectionMethod(ProjectApiKeyController::class, 'settingsBaseUrl');
        self::assertSame(
            'https://beacon.test',
            $baseUrl->invoke($controller, Request::create('https://beacon.test/projects/x/keys')),
        );

        $this->expectException(NotFoundHttpException::class);
        $assert->invoke($controller, $key, $other);
    }
}
