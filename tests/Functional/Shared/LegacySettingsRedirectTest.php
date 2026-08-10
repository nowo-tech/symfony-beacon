<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class LegacySettingsRedirectTest extends DatabaseWebTestCase
{
    public function testLegacySettingsPathsRedirectPermanentlyToAdmin(): void
    {
        [$client, $user] = $this->bootWithDemoProject('legacy-settings-admin@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user->setRoles(['ROLE_ADMIN']);
        $em->flush();

        $this->login($client, $user);

        $cases = [
            '/settings/mailer' => '/admin/mailer',
            '/settings/mercure' => '/admin/mercure',
            '/settings/appearance' => '/admin/appearance',
            '/settings/appearance/themes' => '/admin/appearance/themes',
            '/settings/ops-defaults' => '/admin/ops-defaults',
            '/settings/ops-defaults/governance' => '/admin/ops-defaults/governance',
            '/settings/instance-config' => '/admin/instance-config',
            '/settings/instance-config/export' => '/admin/instance-config/export',
        ];

        foreach ($cases as $from => $to) {
            $client->request(Request::METHOD_GET, $from);
            self::assertResponseStatusCodeSame(Response::HTTP_MOVED_PERMANENTLY, sprintf('Expected 301 for %s', $from));
            self::assertResponseRedirects($to);
        }
    }
}
