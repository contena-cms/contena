<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(JsonField::class)]
class JsonFieldTest extends TestCase
{
    public function testInstantiateWithPropertyMapping(): void
    {
        $productMapping = new JsonField('product', 'product', [
            new IntField('maxSuggestCount', 'maxSuggestCount'),
        ]);
        $field = new JsonField('hit_count', 'hitCount', [$productMapping], ['product' => ['maxSuggestCount' => 10]]);

        static::assertSame('hit_count', $field->getStorageName());
        static::assertSame('hitCount', $field->getPropertyName());
        static::assertSame([$productMapping], $field->getPropertyMapping());
        static::assertSame(['product' => ['maxSuggestCount' => 10]], $field->getDefault());
    }

    public function testAddPropertyMappingAppendsNestedFields(): void
    {
        $productMapping = new JsonField('product', 'product', [
            new IntField('maxSuggestCount', 'maxSuggestCount'),
        ]);
        $field = new JsonField('hit_count', 'hitCount', [$productMapping]);
        $extendedMapping = new JsonField('landing_page', 'landing_page');

        static::assertSame($field, $field->addPropertyMapping($extendedMapping));
        static::assertSame([$productMapping, $extendedMapping], $field->getPropertyMapping());
    }
}
