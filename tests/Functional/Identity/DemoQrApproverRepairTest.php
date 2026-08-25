<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Identity\Entity\User;
use App\Tests\Support\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class DemoQrApproverRepairTest extends DatabaseWebTestCase
{
    public function testAdminCanRestoreDemoQrPhoneVerification(): void
    {
        [$client, $user] = $this->bootWithDemoProject('admin@symfony-beacon.local');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPhone(null);
        $user->setPhoneVerifiedAt(null);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->flush();
        $userId = $user->getId();
        self::assertNotNull($userId);
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/_internal/demo/ensure-qr-approver');
        self::assertResponseStatusCodeSame(204);

        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($userId);
        self::assertInstanceOf(User::class, $reloaded);
        self::assertSame('+34600000000', $reloaded->getPhone());
        self::assertInstanceOf(DateTimeImmutable::class, $reloaded->getPhoneVerifiedAt());
    }

    public function testAnonymousRequestIsNotPublic(): void
    {
        $client = static::createClient();
        $this->seedPlatformCatalogs();
        $client->request(Request::METHOD_GET, '/_internal/demo/ensure-qr-approver');
        $status = $client->getResponse()->getStatusCode();
        self::assertTrue($status >= 300 && $status < 500, 'expected redirect or client error, got '.$status);
    }
}
