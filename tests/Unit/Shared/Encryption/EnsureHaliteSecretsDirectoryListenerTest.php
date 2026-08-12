<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Encryption;

use App\Shared\Encryption\EnsureHaliteSecretsDirectoryListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class EnsureHaliteSecretsDirectoryListenerTest extends TestCase
{
    public function testHardensWorldWritableKeyOnRequest(): void
    {
        $fs = new Filesystem();
        $root = sys_get_temp_dir().'/beacon-halite-'.bin2hex(random_bytes(4));
        $secrets = $root.'/var/secrets';
        $fs->mkdir($secrets);
        $keyFile = $secrets.'/.Halite.default.key';
        file_put_contents($keyFile, 'test-key-material');
        chmod($keyFile, 0666);

        $listener = new EnsureHaliteSecretsDirectoryListener($root);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, new Request(), HttpKernelInterface::MAIN_REQUEST);
        $listener->onKernelRequest($event);

        self::assertSame('0600', \sprintf('%04o', fileperms($keyFile) & 0777));

        $fs->remove($root);
    }
}
