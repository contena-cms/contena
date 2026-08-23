<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader\EntityCollectionLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader\EntityCollectionLoaderConfigSerializer;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfigSerializer;
use Contena\Core\Test\Stub\ContentSystem\StubLoaderConfig;

/**
 * @internal
 */
#[CoversClass(EntityCollectionLoaderConfigSerializer::class)]
class EntityCollectionLoaderConfigSerializerTest extends TestCase
{
    private EntityLoaderConfigSerializer $delegate;

    private EntityCollectionLoaderConfigSerializer $serializer;

    protected function setUp(): void
    {
        $this->delegate = new EntityLoaderConfigSerializer();
        $this->serializer = new EntityCollectionLoaderConfigSerializer($this->delegate);
    }

    #[TestDox('returns entity_collection source identifier')]
    public function testGetSourceReturnsEntityCollectionString(): void
    {
        static::assertSame(EntityCollectionLoader::SOURCE, EntityCollectionLoaderConfigSerializer::getSource());
    }

    #[TestDox('delegates decode and returns entity and property fields')]
    public function testDecodeReturnsEntityAndPropertyFields(): void
    {
        $result = $this->serializer->decode([
            'entity' => 'blog',
            'property' => 'blogId',
        ]);

        static::assertInstanceOf(EntityLoaderConfig::class, $result);
        static::assertSame('blog', $result->entity);
        static::assertSame('blogId', $result->property);
        static::assertSame([], $result->associations);
    }

    #[TestDox('delegates decode and passes through associations')]
    public function testDecodePassesThroughAssociations(): void
    {
        $result = $this->serializer->decode([
            'entity' => 'category',
            'property' => 'categoryId',
            'associations' => ['children', 'seoUrls'],
        ]);

        static::assertInstanceOf(EntityLoaderConfig::class, $result);
        static::assertSame('category', $result->entity);
        static::assertSame('categoryId', $result->property);
        static::assertSame(['children', 'seoUrls'], $result->associations);
    }

    #[TestDox('delegates encode without associations and omits associations key')]
    public function testEncodeWithoutAssociationsOmitsKey(): void
    {
        $result = $this->serializer->encode(new EntityLoaderConfig('blog', 'blogId', []));

        static::assertSame(['entity' => 'blog', 'property' => 'blogId'], $result);
    }

    #[TestDox('delegates encode with associations and includes associations key')]
    public function testEncodeWithAssociationsIncludesKey(): void
    {
        $result = $this->serializer->encode(
            new EntityLoaderConfig('category', 'categoryId', ['children', 'seoUrls'])
        );

        static::assertSame([
            'entity' => 'category',
            'property' => 'categoryId',
            'associations' => ['children', 'seoUrls'],
        ], $result);
    }

    #[TestDox('round-trips configuration through encode and decode unchanged')]
    public function testDecodeAndEncodeAreInverse(): void
    {
        $original = [
            'entity' => 'blog',
            'property' => 'blogId',
            'associations' => ['tags', 'cover'],
        ];

        $config = $this->serializer->decode($original);
        $encoded = $this->serializer->encode($config);

        static::assertSame($original, $encoded);
    }

    #[TestDox('propagates exception from delegate when entity key is missing')]
    public function testDecodePropagatesDelegateExceptionForMissingEntity(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('entity', 'non-empty string', 'NULL')
        );

        $this->serializer->decode(['property' => 'blogId']);
    }

    #[TestDox('propagates exception from delegate when property key is missing')]
    public function testDecodePropagatesDelegateExceptionForMissingProperty(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('property', 'non-empty string', 'NULL')
        );

        $this->serializer->decode(['entity' => 'blog']);
    }

    #[TestDox('propagates exception from delegate when config type is wrong')]
    public function testEncodePropagatesDelegateExceptionForWrongConfigType(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('config', EntityLoaderConfig::class, StubLoaderConfig::class)
        );

        $this->serializer->encode(new StubLoaderConfig());
    }
}
