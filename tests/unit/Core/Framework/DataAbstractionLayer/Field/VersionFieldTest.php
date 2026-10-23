<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\VersionField;

/**
 * @internal
 */
#[CoversClass(VersionField::class)]
class VersionFieldTest extends TestCase
{
    public function testConstructorConfiguresTheVersionForeignKey(): void
    {
        $field = new VersionField();

        static::assertSame('version_id', $field->getStorageName());
        static::assertSame('versionId', $field->getPropertyName());
        static::assertTrue($field->is(PrimaryKey::class));
        static::assertTrue($field->is(Required::class));
    }
}
