<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\ContentSystem\DataLoader;

use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogListingLoaderConfig;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogListingLoaderConfigSerializer;
use Contena\Core\Test\Stub\ContentSystem\StubLoaderConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWithJson;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BlogListingLoaderConfigSerializer::class)]
class BlogListingLoaderConfigSerializerTest extends TestCase
{
    private BlogListingLoaderConfigSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new BlogListingLoaderConfigSerializer();
    }

    #[TestDox('returns blog_listing source identifier')]
    public function testGetSourceReturnsBlogListingString(): void
    {
        static::assertSame('blog_listing', BlogListingLoaderConfigSerializer::getSource());
    }

    #[TestDox('decodes empty array into BlogListingLoaderConfig with null property')]
    public function testDecodeEmptyArrayReturnsBlogListingLoaderConfigWithNullProperty(): void
    {
        $result = $this->serializer->decode([]);

        static::assertInstanceOf(BlogListingLoaderConfig::class, $result);
        static::assertNull($result->property);
    }

    #[TestDox('decodes config with valid property into BlogListingLoaderConfig with property set')]
    public function testDecodeWithValidPropertySetsProperty(): void
    {
        $result = $this->serializer->decode(['property' => 'myProperty']);

        static::assertInstanceOf(BlogListingLoaderConfig::class, $result);
        static::assertSame('myProperty', $result->property);
        static::assertSame([], $result->associations);
    }

    #[TestDox('decodes config with valid associations into BlogListingLoaderConfig with associations set')]
    public function testDecodeWithValidAssociationsSetsAssociations(): void
    {
        $result = $this->serializer->decode(['associations' => ['manufacturer', 'categories']]);

        static::assertInstanceOf(BlogListingLoaderConfig::class, $result);
        static::assertNull($result->property);
        static::assertSame(['manufacturer', 'categories'], $result->associations);
    }

    #[TestDox('decodes config with both property and associations into BlogListingLoaderConfig with all values')]
    public function testDecodeWithAllFieldsReturnsBlogListingLoaderConfigWithAllValues(): void
    {
        $result = $this->serializer->decode([
            'property' => 'listingProperty',
            'associations' => ['media', 'options'],
        ]);

        static::assertInstanceOf(BlogListingLoaderConfig::class, $result);
        static::assertSame('listingProperty', $result->property);
        static::assertSame(['media', 'options'], $result->associations);
    }

    #[TestDox('decodes null associations into BlogListingLoaderConfig with empty associations')]
    public function testDecodeNullAssociationsReturnsEmptyAssociations(): void
    {
        $result = $this->serializer->decode(['associations' => null]);

        static::assertInstanceOf(BlogListingLoaderConfig::class, $result);
        static::assertSame([], $result->associations);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[TestWithJson('[{"property": ""}, "string"]', 'property is empty string')]
    #[TestWithJson('[{"property": 42}, "integer"]', 'property is non-string type')]
    #[TestDox('throws exception when property is invalid')]
    public function testDecodeWithInvalidPropertyThrowsException(array $data, string $actualType): void
    {
        $this->expectExceptionObject(
            BlogException::invalidFieldValueType('property', 'non-empty string', $actualType)
        );

        $this->serializer->decode($data);
    }

    #[TestDox('throws exception when associations is not an array')]
    public function testDecodeWithNonArrayAssociationsThrowsException(): void
    {
        $this->expectExceptionObject(
            BlogException::invalidFieldValueType('associations', 'array', 'string')
        );

        $this->serializer->decode(['associations' => 'manufacturer']);
    }

    #[TestDox('throws exception when first association item is an empty string')]
    public function testDecodeWithEmptyStringFirstAssociationItemThrowsException(): void
    {
        $this->expectExceptionObject(
            BlogException::invalidFieldValueType('associations.0', 'non-empty string', 'string')
        );

        $this->serializer->decode(['associations' => ['']]);
    }

    #[TestDox('throws exception when second association item is an empty string')]
    public function testDecodeWithEmptyStringSecondAssociationItemThrowsException(): void
    {
        $this->expectExceptionObject(
            BlogException::invalidFieldValueType('associations.1', 'non-empty string', 'string')
        );

        $this->serializer->decode(['associations' => ['manufacturer', '']]);
    }

    #[TestDox('throws exception when first association item is a non-string type')]
    public function testDecodeWithNonStringFirstAssociationItemThrowsException(): void
    {
        $this->expectExceptionObject(
            BlogException::invalidFieldValueType('associations.0', 'non-empty string', 'integer')
        );

        $this->serializer->decode(['associations' => [42]]);
    }

    #[TestDox('encodes BlogListingLoaderConfig with defaults into empty array')]
    public function testEncodeConfigWithDefaultsReturnsEmptyArray(): void
    {
        $config = new BlogListingLoaderConfig();

        $result = $this->serializer->encode($config);

        static::assertSame([], $result);
    }

    #[TestDox('encodes BlogListingLoaderConfig with property into array containing property key')]
    public function testEncodeConfigWithPropertyIncludesPropertyKey(): void
    {
        $config = new BlogListingLoaderConfig(property: 'listingProp');

        $result = $this->serializer->encode($config);

        static::assertSame(['property' => 'listingProp'], $result);
    }

    #[TestDox('encodes BlogListingLoaderConfig with associations into array containing associations key')]
    public function testEncodeConfigWithAssociationsIncludesAssociationsKey(): void
    {
        $config = new BlogListingLoaderConfig(associations: ['media', 'options']);

        $result = $this->serializer->encode($config);

        static::assertSame(['associations' => ['media', 'options']], $result);
    }

    #[TestDox('encodes BlogListingLoaderConfig with property and associations into full array')]
    public function testEncodeConfigWithAllFieldsReturnsFullArray(): void
    {
        $config = new BlogListingLoaderConfig(
            property: 'listingProp',
            associations: ['manufacturer', 'categories'],
        );

        $result = $this->serializer->encode($config);

        static::assertSame([
            'property' => 'listingProp',
            'associations' => ['manufacturer', 'categories'],
        ], $result);
    }

    #[TestDox('decodes a valid associationOverride into the config')]
    public function testDecodeWithValidAssociationOverrideSetsAssociationOverride(): void
    {
        $result = $this->serializer->decode(['associationOverride' => 'extraAssociations']);

        static::assertInstanceOf(BlogListingLoaderConfig::class, $result);
        static::assertSame('extraAssociations', $result->associationOverride);
    }

    #[TestDox('decodes a config without associationOverride into a null associationOverride')]
    public function testDecodeWithoutAssociationOverrideLeavesItNull(): void
    {
        $result = $this->serializer->decode([]);

        static::assertInstanceOf(BlogListingLoaderConfig::class, $result);
        static::assertNull($result->associationOverride);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[TestWithJson('[{"associationOverride": ""}, "string"]', 'associationOverride is empty string')]
    #[TestWithJson('[{"associationOverride": 42}, "integer"]', 'associationOverride is non-string type')]
    #[TestDox('throws exception when associationOverride is invalid')]
    public function testDecodeWithInvalidAssociationOverrideThrowsException(array $data, string $actualType): void
    {
        $this->expectExceptionObject(
            BlogException::invalidFieldValueType('associationOverride', 'non-empty string', $actualType)
        );

        $this->serializer->decode($data);
    }

    /**
     * @param array<string, mixed> $original
     */
    #[DataProvider('roundTripProvider')]
    #[TestDox('round-trips $_dataName without data loss')]
    public function testDecodeAndEncodeAreInverse(array $original): void
    {
        $config = $this->serializer->decode($original);
        $encoded = $this->serializer->encode($config);

        static::assertSame($original, $encoded);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function roundTripProvider(): iterable
    {
        yield 'empty config' => [[]];
        yield 'property only' => [['property' => 'categoryProperty']];
        yield 'associations only' => [['associations' => ['options', 'cover']]];
        yield 'association override only' => [['associationOverride' => 'extraAssociations']];
        yield 'full config' => [
            ['property' => 'myProperty', 'associations' => ['manufacturer', 'media']],
        ];
    }

    #[TestDox('throws exception when encoding a non-BlogListingLoaderConfig config instance')]
    public function testEncodeWithWrongConfigTypeThrowsException(): void
    {
        $this->expectExceptionObject(
            BlogException::invalidFieldValueType('config', BlogListingLoaderConfig::class, StubLoaderConfig::class)
        );

        $this->serializer->encode(new StubLoaderConfig());
    }
}
