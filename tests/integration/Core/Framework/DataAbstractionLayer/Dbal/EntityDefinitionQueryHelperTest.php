<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\ContentStream\ContentStreamDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;

/**
 * @internal
 */
class EntityDefinitionQueryHelperTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @param array<string> $expected
     */
    #[DataProvider('getData')]
    public function testGetFieldsOfAccessor(
        string $class,
        string $accessor,
        array $expected,
        bool $resolveTranslated = true
    ): void {
        $definition = static::getContainer()->get(DefinitionInstanceRegistry::class)->get($class);
        $fields = EntityDefinitionQueryHelper::getFieldsOfAccessor($definition, $accessor, $resolveTranslated);

        $actual = [];
        foreach ($fields as $field) {
            $actual[] = $field->getPropertyName();
        }

        static::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array<int, array<int, string>|string|false>>
     */
    public static function getData(): iterable
    {
        yield 'With blog prefix and skippable fields' => [
            BlogDefinition::class,
            'blog.options.first.group.extension.foo.bar',
            [
                'options',
                'group',
            ],
        ];

        yield 'With skippable field' => [
            BlogDefinition::class,
            'options.first.group.name',
            [
                'options',
                'group',
                'name',
            ],
        ];

        yield 'With translations field' => [
            BlogDefinition::class,
            'translations.description',
            [
                'translations',
                'description',
            ],
        ];

        yield 'With resolved translated field' => [
            ContentStreamDefinition::class,
            'description.invalid',
            [
                'description',
            ],
        ];

        yield 'Without resolved translated field' => [
            ContentStreamDefinition::class,
            'description.invalid',
            [
                'description',
                'invalid',
            ],
            false,
        ];

        yield 'With custom fields without valid field behind' => [
            BlogDefinition::class,
            'customFields.foo.bar',
            [
                'customFields',
            ],
        ];

        yield 'With custom fields with valid field behind' => [
            BlogDefinition::class,
            'customFields.name',
            [
                'customFields',
                'name',
            ],
        ];

        yield 'With json field without valid field behind' => [
            BlogDefinition::class,
            'variantListingConfig.foo.bar',
            [
                'variantListingConfig',
            ],
        ];
    }
}
