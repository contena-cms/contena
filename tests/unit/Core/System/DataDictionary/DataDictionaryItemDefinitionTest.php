<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\DataDictionary;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryItem\DataDictionaryItemDefinition;

/**
 * @internal
 */
#[CoversClass(DataDictionaryItemDefinition::class)]
class DataDictionaryItemDefinitionTest extends TestCase
{
    public function testDefinesTheDenormalizedTreeFields(): void
    {
        $definition = new DataDictionaryItemDefinition();
        $method = new \ReflectionMethod(DataDictionaryItemDefinition::class, 'defineFields');
        /** @var FieldCollection $fields */
        $fields = $method->invoke($definition);

        static::assertCount(1, $fields->filterInstance(TreeLevelField::class));
        static::assertCount(1, $fields->filterInstance(TreePathField::class));
        static::assertCount(1, $fields->filterInstance(ChildCountField::class));
    }
}
