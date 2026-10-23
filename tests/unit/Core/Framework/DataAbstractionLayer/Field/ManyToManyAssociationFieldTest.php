<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(ManyToManyAssociationField::class)]
class ManyToManyAssociationFieldTest extends TestCase
{
    public function testConstructorConfiguresTheMappingColumns(): void
    {
        $field = new ManyToManyAssociationField(
            'categories',
            CategoryDefinition::class,
            BlogCategoryDefinition::class,
            'blog_id',
            'category_id'
        );

        static::assertSame('categories', $field->getPropertyName());
        static::assertSame(BlogCategoryDefinition::class, $field->getReferenceClass());
        static::assertSame('id', $field->getReferenceField());
        static::assertSame('blog_id', $field->getMappingLocalColumn());
        static::assertSame('category_id', $field->getMappingReferenceColumn());
        static::assertSame('id', $field->getLocalField());
    }
}
