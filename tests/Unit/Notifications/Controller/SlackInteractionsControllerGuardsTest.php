<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Controller;

use App\Identity\Repository\UserRepository;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\IssueAssigneeChanger;
use App\Issues\Service\IssueStatusChanger;
use App\Notifications\Controller\SlackInteractionsController;
use App\Notifications\Service\HookDestinationContextResolver;
use App\Notifications\Service\HookMutationPolicy;
use App\Notifications\Service\SlackRequestSignatureVerifier;
use App\Project\Service\ProjectAccessService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SlackInteractionsControllerGuardsTest extends TestCase
{
    public function testMissingPayloadReturnsBadRequest(): void
    {
        $response = $this->controller()->__invoke(Request::create('/hooks/slack/interactions', Request::METHOD_POST));
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('Missing payload', $response->getContent());
    }

    public function testInvalidJsonPayloadReturnsBadRequest(): void
    {
        $response = $this->controller()->__invoke(Request::create(
            '/hooks/slack/interactions',
            Request::METHOD_POST,
            ['payload' => '{not-json'],
        ));
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('Invalid payload', $response->getContent());
    }

    public function testUrlVerificationOnInteractionsIsRejected(): void
    {
        $response = $this->controller()->__invoke(Request::create(
            '/hooks/slack/interactions',
            Request::METHOD_POST,
            ['payload' => json_encode(['type' => 'url_verification', 'challenge' => 'abc'], \JSON_THROW_ON_ERROR)],
        ));
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('Unsupported on interactions endpoint', $response->getContent());
    }

    private function controller(): SlackInteractionsController
    {
        return new SlackInteractionsController(
            new ReflectionClass(SlackRequestSignatureVerifier::class)->newInstanceWithoutConstructor(),
            new ReflectionClass(HookDestinationContextResolver::class)->newInstanceWithoutConstructor(),
            $this->createStub(IssueRepository::class),
            $this->createStub(UserRepository::class),
            new ReflectionClass(IssueStatusChanger::class)->newInstanceWithoutConstructor(),
            new ReflectionClass(IssueAssigneeChanger::class)->newInstanceWithoutConstructor(),
            new ReflectionClass(ProjectAccessService::class)->newInstanceWithoutConstructor(),
            $this->createStub(EntityManagerInterface::class),
            new NullLogger(),
            new ReflectionClass(HookMutationPolicy::class)->newInstanceWithoutConstructor(),
        );
    }
}
