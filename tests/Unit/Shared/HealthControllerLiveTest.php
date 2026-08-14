<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Health\HealthController;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Response;

final class HealthControllerLiveTest extends TestCase
{
    public function testLiveAlwaysOkWithoutTouchingDatabase(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('getConnection');

        $response = new HealthController($em, new NullLogger())->live();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(['status' => 'ok'], json_decode((string) $response->getContent(), true));
    }
}
