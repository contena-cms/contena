<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWithJson;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogSearchLoaderConfig;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogSearchLoaderConfigSerializer;
use Contena\Core\Test\Stub\ContentSystem\StubLoaderConfig;

/**
 * @internal
 */
#[CoversClass(BlogSearchLoaderConfigSerializer::class)]
class BlogSearchLoaderConfigSerializerTest extends TestCase
{
    private BlogSearchLoaderConfigSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new BlogSearchLoaderConfigSerializer();
    }

    #[TestDox('returns blog_search source identifier')]
    public function testGetSourceReturnsBlogSearchString(): void
    {
        static::assertSame('blog_search', BlogSearchLoaderConfigSerializer::getSource());
    }

    #[TestDox('decodes empty array into BlogSearchLoaderConfig with null searchTermProperty')]
    public function testDecodeEmptyArrayReturnsConfigWithNullSearchTermProperty(): void
    {
        $result = $this->serializer->decode([]);

        static::assertInstanceOf(BlogSearchLoaderConfig::class, $result);
        static::assertNull($result->searchTermProperty);
        static::assertSame([], $result->associations);
    }

    #[TestDox('decodes config with valid searchTermProperty into config with property set')]
    public function testDecodeWithValidSearchTermPropertySetsProperty(): void
    {
        $result = $this->serializer->decode(['searchTermProperty' => 'query']);

        static::assertInstanceOf(BlogSearchLoaderConfig::class, $result);
        static::assertSame('query', $result->searchTermProperty);
        static::assertSame([], $result->associations);
    }

    #[TestDox('decodes config with valid associations into config with associations set')]
    public function testDecodeWithValidAssociationsSetsAssociations(): void
    {
        $result = $this->serializer->decode(['associations' => ['tags', 'categories']]);

        static::assertInstanceOf(BlogSearchLoaderConfig::class, $result);
        static::assertNull($result->searchTermProperty);
        static::assertSame(['tags', 'categories'], $result->associations);
    }

    #[TestDox('decodes config with both searchTermProperty and associations into config with all values')]
    public function testDecodeWithAllFieldsReturnsConfigWithAllValues(): void
    {
        $result = $this->serializer->decode([
            'searchTermProperty' => 'searchQuery',
            'associations' => ['media', 'tags'],
        ]);

        static::assertInstanceOf(BlogSearchLoaderConfig::class, $result);
        static::assertSame('searchQuery', $result->searchTermProperty);
        static::assertSame(['media', 'tags'], $result->associations);
    }

    #[TestDox('decodes null associations into config with empty associations')]
    public function testDecodeNullAssociationsReturnsEmptyAssociations(): void
    {
        $result = $this->serializer->decode(['associations' => null]);

        static::assertInstanceOf(BlogSearchLoaderConfig::class, $result);
        static::assertSame([], $result->associations);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[TestWithJson('[{"searchTermProperty": ""}, "string"]', 'searchTermProperty is empty string')]
    #[TestWithJson('[{"searchTermProperty": 42}, "integer"]', 'searchTermProperty is non-string type')]
    #[TestDox('throws exception when searchTermProperty is invalid')]
    public function testDecodeWithInvalidSearchTermPropertyThrowsException(array $data, string $actualType): void
    {
        $this->expectExceptionObject(
            BlogException::invalidFieldValueType('searchTermProperty', 'non-empty string', $actualType)
        );

        $this->serializer->decode($data);
    }

    #[TestDox('throws exception when associations is not an array')]
    public function testDecodeWithNonArrayAssociationsThrowsException(): void
    {
        $this->expectExceptionObject(
            BlogException::invalidFieldValueType('associations', 'array', 'string')
        );

        $this->serializer->decode(['associations' => 'tags']);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidAssociationItemProvider')]
    #[TestDox('throws exception when association item is invalid: $_dataName')]
    public function testDecodeWithInvalidAssociationItemThrowsException(array $data, string $field, string $actualType): void
    {
        $this->expectExceptionObject(
            BlogException::invalidFieldValueType($field, 'non-empty string', $actualType)
        );

        $this->serializer->decode($data);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidAssociationItemProvider(): iterable
    {
        yield 'empty string triggers empty guard' => [
            ['associations' => ['']], 'associations.0', 'string',
        ];
        yield 'non-zero index correctly reported in field path' => [
            ['associations' => ['tags', '']], 'associations.1', 'string',
        ];
        yield 'non-string type triggers type guard' => [
            ['associations' => [42]], 'associations.0', 'integer',
        ];
    }

    #[TestDox('encodes config with defaults into empty array')]
    public function testEncodeConfigWithDefaultsReturnsEmptyArray(): void
    {
        $config = new BlogSearchLoaderConfig();

        $result = $this->serializer->encode($config);

        static::assertSame([], $result);
    }

    #[TestDox('encodes config with searchTermProperty into array containing searchTermProperty key')]
    public function testEncodeConfigWithSearchTermPropertyIncludesKey(): void
    {
        $config = new BlogSearchLoaderConfig(searchTermProperty: 'query');

        $result = $this->serializer->encode($config);

        static::assertSame(['searchTermProperty' => 'query'], $result);
    }

    #[TestDox('encodes config with associations into array containing associations key')]
    public function testEncodeConfigWithAssociationsIncludesAssociationsKey(): void
    {
        $config = new BlogSearchLoaderConfig(associations: ['media', 'tags']);

        $result = $this->serializer->encode($config);

        static::assertSame(['associations' => ['media', 'tags']], $result);
    }

    #[TestDox('encodes config with searchTermProperty and associations into full array')]
    public function testEncodeConfigWithAllFieldsReturnsFullArray(): void
    {
        $config = new BlogSearchLoaderConfig(
            searchTermProperty: 'query',
            associations: ['tags', 'categories'],
        );

        $result = $this->serializer->encode($config);

        static::assertSame([
            'searchTermProperty' => 'query',
            'associations' => ['tags', 'categories'],
        ], $result);
    }

    /**
     * @param array<string, mixed> $original
     */
    #[DataProvider('roundTripsProvider')]
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
    public static function roundTripsProvider(): iterable
    {
        yield 'empty config' => [[]];
        yield 'searchTermProperty only' => [['searchTermProperty' => 'query']];
        yield 'associations only' => [['associations' => ['tags', 'cover']]];
        yield 'full config' => [
            ['searchTermProperty' => 'myQuery', 'associations' => ['tags', 'media']],
        ];
    }

    #[TestDox('throws exception when encoding a non-BlogSearchLoaderConfig config instance')]
    public function testEncodeWithWrongConfigTypeThrowsException(): void
    {
        $this->expectExceptionObject(
            BlogException::invalidFieldValueType('config', BlogSearchLoaderConfig::class, StubLoaderConfig::class)
        );

        $this->serializer->encode(new StubLoaderConfig());
    }
}
