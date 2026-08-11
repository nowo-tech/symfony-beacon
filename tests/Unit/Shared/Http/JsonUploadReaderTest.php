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
