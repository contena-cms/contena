<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;

/**
 * @internal
 */
#[CoversClass(ChildrenAssociationField::class)]
class ChildrenAssociationFieldTest extends TestCase
{
    public function testConstructorConfiguresTheParentIdAssociation(): void
    {
        $field = new ChildrenAssociationField(CategoryDefinition::class);

        static::assertSame('children', $field->getPropertyName());
        static::assertSame(CategoryDefinition::class, $field->getReferenceClass());
        static::assertSame('parent_id', $field->getReferenceField());
        static::assertSame('id', $field->getLocalField());
        static::assertTrue($field->is(CascadeDelete::class));
    }
}
