<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;

/**
 * @internal
 */
#[CoversClass(JsonField::class)]
class JsonFieldTest extends TestCase
{
    public function testInstantiateWithPropertyMapping(): void
    {
        $blogMapping = new JsonField('blog', 'blog', [
            new IntField('maxSuggestCount', 'maxSuggestCount'),
        ]);
        $field = new JsonField('hit_count', 'hitCount', [$blogMapping], ['blog' => ['maxSuggestCount' => 10]]);

        static::assertSame('hit_count', $field->getStorageName());
        static::assertSame('hitCount', $field->getPropertyName());
        static::assertSame([$blogMapping], $field->getPropertyMapping());
        static::assertSame(['blog' => ['maxSuggestCount' => 10]], $field->getDefault());
    }

    public function testAddPropertyMappingAppendsNestedFields(): void
    {
        $blogMapping = new JsonField('blog', 'blog', [
            new IntField('maxSuggestCount', 'maxSuggestCount'),
        ]);
        $field = new JsonField('hit_count', 'hitCount', [$blogMapping]);

        $extendedMapping = new JsonField('landing_page', 'landing_page', [
            new IntField('maxSuggestCount', 'maxSuggestCount'),
            new IntField('maxSearchCount', 'maxSearchCount'),
        ]);

        $returned = $field->addPropertyMapping($extendedMapping);

        static::assertSame($field, $returned);
        static::assertSame([$blogMapping, $extendedMapping], $field->getPropertyMapping());
    }
}
