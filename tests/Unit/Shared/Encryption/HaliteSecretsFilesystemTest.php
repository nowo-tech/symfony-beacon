<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Encryption;

use App\Shared\Encryption\HaliteSecretsFilesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class HaliteSecretsFilesystemTest extends TestCase
{
    public function testNativeFilesystemHelpersRoundTrip(): void
    {
        $fs = new Filesystem();
        $root = sys_get_temp_dir().'/beacon-halite-fs-'.bin2hex(random_bytes(4));
        $fs->mkdir($root);
        $file = $root.'/.Halite.default.key';
        file_put_contents($file, 'k');
        chmod($file, 0666);

        $native = new HaliteSecretsFilesystem();
        self::assertTrue($native->isDirectory($root));
        self::assertTrue($native->isFile($file));
        self::assertNotFalse($native->filePerms($file));
        self::assertTrue($native->chmod($file, 0600));
        $matches = $native->glob($root.'/.Halite*.key');
        self::assertIsArray($matches);
        self::assertContains($file, $matches);
        self::assertTrue($native->makeDirectory($root.'/nested', 0770));

        $fs->remove($root);
    }
}
