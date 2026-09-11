<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Validation\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Validation\Error\ContentSystemLayoutPresetSchemaError;

/**
 * @internal
 */
#[CoversClass(ContentSystemLayoutPresetSchemaError::class)]
class ContentSystemLayoutPresetSchemaErrorTest extends TestCase
{
    #[TestDox('formats message with filename and reason')]
    public function testFormatsMessageWithFilenameAndReason(): void
    {
        $error = new ContentSystemLayoutPresetSchemaError('/path/to/presets', 'YAML syntax invalid');

        static::assertSame('Invalid layout preset in "/path/to/presets": YAML syntax invalid', $error->getMessage());
    }

    #[TestDox('returns correct message key')]
    public function testReturnsCorrectMessageKey(): void
    {
        $error = new ContentSystemLayoutPresetSchemaError('file.yaml', 'reason');

        static::assertSame('manifest-invalid-layout-preset-schema', $error->getMessageKey());
    }
}
