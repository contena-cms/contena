<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;

/**
 * @internal
 */
#[CoversClass(ReferenceVersionField::class)]
class ReferenceVersionFieldTest extends TestCase
{
    public function testConstructorDerivesStorageAndPropertyNameFromTheDefinition(): void
    {
        $field = new ReferenceVersionField(BlogDefinition::class);

        static::assertSame('blog_version_id', $field->getStorageName());
        static::assertSame('blogVersionId', $field->getPropertyName());
        static::assertSame(BlogDefinition::class, $field->getVersionReferenceClass());
    }

    public function testConstructorUsesAnExplicitStorageName(): void
    {
        $field = new ReferenceVersionField(BlogDefinition::class, 'origin_version_id');

        static::assertSame('origin_version_id', $field->getStorageName());
        static::assertSame('originVersionId', $field->getPropertyName());
    }
}
