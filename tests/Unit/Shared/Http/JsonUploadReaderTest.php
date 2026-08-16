<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Http;

use App\Shared\Http\JsonUploadReader;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class JsonUploadReaderTest extends TestCase
{
    public function testDecodeObjectAcceptsSmallJsonObject(): void
    {
        $file = $this->tempUpload('{"schema":"x","n":1}');
        $payload = JsonUploadReader::decodeObject($file, 1024);
        self::assertSame(['schema' => 'x', 'n' => 1], $payload);
    }

    public function testDecodeObjectRejectsMissingFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing_file');
        JsonUploadReader::decodeObject(null);
    }

    public function testDecodeObjectRejectsTooLarge(): void
    {
        $file = $this->tempUpload(str_repeat('a', 200));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('too_large');
        JsonUploadReader::decodeObject($file, 100);
    }

    public function testDecodeObjectRejectsInvalidJson(): void
    {
        $file = $this->tempUpload('not-json');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid_json');
        JsonUploadReader::decodeObject($file, 1024);
    }

    public function testDecodeObjectRejectsJsonArrayRoot(): void
    {
        $file = $this->tempUpload('[1,2]');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid_json');
        JsonUploadReader::decodeObject($file, 1024);
    }

    public function testDecodeObjectRejectsUnreadableAndUndecodablePaths(): void
    {
        $unreadable = $this->createMock(UploadedFile::class);
        $unreadable->method('isValid')->willReturn(true);
        $unreadable->method('getSize')->willReturn(10);
        $unreadable->method('getPathname')->willReturn('/definitely/missing.json');

        try {
            JsonUploadReader::decodeObject($unreadable, 1024);
            self::fail('Expected missing_file for unreadable path');
        } catch (InvalidArgumentException $e) {
            self::assertSame('missing_file', $e->getMessage());
        }

        $directoryPath = sys_get_temp_dir().'/beacon-json-dir-'.bin2hex(random_bytes(4));
        mkdir($directoryPath);
        try {
            $directory = $this->createMock(UploadedFile::class);
            $directory->method('isValid')->willReturn(true);
            $directory->method('getSize')->willReturn(10);
            $directory->method('getPathname')->willReturn($directoryPath);

            set_error_handler(static fn (): bool => true);
            JsonUploadReader::decodeObject($directory, 1024);
            self::fail('Expected missing_file for non-readable contents');
        } catch (InvalidArgumentException $e) {
            self::assertSame('invalid_json', $e->getMessage());
        } finally {
            restore_error_handler();
            rmdir($directoryPath);
        }
    }

    public function testDecodeObjectRejectsPayloadThatExceedsLimitAfterRead(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'beacon-json-upload-oversize-');
        self::assertNotFalse($path);
        file_put_contents($path, '{"ab":1}');

        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getSize')->willReturn(1);
        $file->method('getPathname')->willReturn($path);

        try {
            JsonUploadReader::decodeObject($file, 4);
            self::fail('Expected too_large after reading file contents');
        } catch (InvalidArgumentException $e) {
            self::assertSame('too_large', $e->getMessage());
        } finally {
            @unlink($path);
        }
    }

    private function tempUpload(string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'beacon-json-upload-');
        self::assertNotFalse($path);
        file_put_contents($path, $contents);

        return new UploadedFile(
            $path,
            'bundle.json',
            'application/json',
            null,
            true,
        );
    }
}
