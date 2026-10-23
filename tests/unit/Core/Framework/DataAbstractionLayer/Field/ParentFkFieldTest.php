<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentFkField;

/**
 * @internal
 */
#[CoversClass(ParentFkField::class)]
class ParentFkFieldTest extends TestCase
{
    public function testConstructorConfiguresTheParentForeignKey(): void
    {
        $field = new ParentFkField('some-reference-class');

        static::assertSame('parent_id', $field->getStorageName());
        static::assertSame('parentId', $field->getPropertyName());
        static::assertSame('id', $field->getReferenceField());
    }
}
