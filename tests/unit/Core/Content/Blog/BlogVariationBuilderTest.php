<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\BlogVariationBuilder;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Contena\Core\Content\Property\PropertyGroupEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(BlogVariationBuilder::class)]
class BlogVariationBuilderTest extends TestCase
{
    /**
     * @param array<array<string, string>> $expected
     */
    #[DataProvider('buildingProvider')]
    public function testBuilding(Entity $blog, array $expected): void
    {
        $builder = new BlogVariationBuilder();

        $builder->build($blog);

        static::assertSame($expected, $blog->get('variation'));
    }

    public static function buildingProvider(): \Generator
    {
        yield 'Test without options' => [
            new BlogEntity(),
            [],
        ];

        yield 'Test without loaded option groups' => [
            (new BlogEntity())->assign([
                '_uniqueIdentifier' => Uuid::randomHex(),
                'options' => new PropertyGroupOptionCollection([
                    (new PropertyGroupOptionEntity())->assign([
                        '_uniqueIdentifier' => Uuid::randomHex(),
                        'name' => 'red',
                    ]),
                ]),
            ]),
            [],
        ];

        yield 'Test with valid blog' => [
            (new BlogEntity())->assign([
                '_uniqueIdentifier' => Uuid::randomHex(),
                'options' => new PropertyGroupOptionCollection([
                    (new PropertyGroupOptionEntity())->assign([
                        '_uniqueIdentifier' => Uuid::randomHex(),
                        'translated' => ['name' => 'red'],
                        'group' => (new PropertyGroupEntity())->assign([
                            '_uniqueIdentifier' => Uuid::randomHex(),
                            'translated' => ['name' => 'color'],
                        ]),
                    ]),
                ]),
            ]),
            [
                ['group' => 'color', 'option' => 'red'],
            ],
        ];

        yield 'Test with partial entity' => [
            (new PartialEntity())->assign([
                '_uniqueIdentifier' => Uuid::randomHex(),
                'options' => new EntityCollection([
                    (new PartialEntity())->assign([
                        'id' => Uuid::randomHex(),
                        '_uniqueIdentifier' => Uuid::randomHex(),
                        'translated' => ['name' => 'red'],
                        'group' => (new PartialEntity())->assign([
                            '_uniqueIdentifier' => Uuid::randomHex(),
                            'translated' => ['name' => 'color'],
                        ]),
                    ]),
                ]),
            ]),
            [
                ['group' => 'color', 'option' => 'red'],
            ],
        ];

        yield 'Test with multiple options, sorted by position' => [
            (new PartialEntity())->assign([
                '_uniqueIdentifier' => Uuid::randomHex(),
                'options' => new EntityCollection([
                    (new PartialEntity())->assign([
                        'id' => Uuid::randomHex(),
                        '_uniqueIdentifier' => Uuid::randomHex(),
                        'translated' => ['name' => 'red'],
                        'group' => (new PartialEntity())->assign([
                            '_uniqueIdentifier' => Uuid::randomHex(),
                            'translated' => ['name' => 'color'],
                            'position' => 2,
                        ]),
                    ]),
                    (new PartialEntity())->assign([
                        'id' => Uuid::randomHex(),
                        '_uniqueIdentifier' => Uuid::randomHex(),
                        'translated' => ['name' => 'xl'],
                        'group' => (new PartialEntity())->assign([
                            '_uniqueIdentifier' => Uuid::randomHex(),
                            'translated' => ['name' => 'size'],
                            'position' => 1,
                        ]),
                    ]),
                ]),
            ]),
            [
                ['group' => 'size', 'option' => 'xl'],
                ['group' => 'color', 'option' => 'red'],
            ],
        ];

        yield 'Test with multiple options, sorted by group name' => [
            (new PartialEntity())->assign([
                '_uniqueIdentifier' => Uuid::randomHex(),
                'options' => new EntityCollection([
                    (new PartialEntity())->assign([
                        'id' => Uuid::randomHex(),
                        '_uniqueIdentifier' => Uuid::randomHex(),
                        'translated' => ['name' => 'xl'],
                        'group' => (new PartialEntity())->assign([
                            '_uniqueIdentifier' => Uuid::randomHex(),
                            'translated' => ['name' => 'size'],
                            'position' => 1,
                        ]),
                    ]),
                    (new PartialEntity())->assign([
                        'id' => Uuid::randomHex(),
                        '_uniqueIdentifier' => Uuid::randomHex(),
                        'translated' => ['name' => 'red'],
                        'group' => (new PartialEntity())->assign([
                            '_uniqueIdentifier' => Uuid::randomHex(),
                            'translated' => ['name' => 'color'],
                            'position' => 1,
                        ]),
                    ]),
                ]),
            ]),
            [
                ['group' => 'color', 'option' => 'red'],
                ['group' => 'size', 'option' => 'xl'],
            ],
        ];
    }
}
