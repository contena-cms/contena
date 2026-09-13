<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Layout\Type;

use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Contena\Core\Framework\ContentSystem\Layout\Type\StoredDefaultProvider;
use Contena\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(StoredDefaultProvider::class)]
class StoredDefaultProviderTest extends TestCase
{
    #[TestDox('returns top-level defaults, skipping properties without defaults and references')]
    public function testForTypeSkipsNullDefaultsAndReferences(): void
    {
        $specs = [
            'Ct:Mixed' => ContentSystemElementTypeSpecificationBuilder::create('Ct:Mixed')
                ->primitive('withDefault', 'string', default: 'seeded')
                ->primitive('noDefault', 'string', required: true)
                ->reference('blog', ChannelBlogEntity::class)
                ->build(),
        ];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        static::assertSame(['withDefault' => 'seeded'], new StoredDefaultProvider()->forType($registry, 'Ct:Mixed'));
    }

    #[TestDox('returns nested object member defaults')]
    public function testForTypeReturnsNestedObjectDefaults(): void
    {
        $nestedProperties = [
            'xs' => new PropertySpecification('xs', new PropertyType('string', false, null, '0 20px 0 20px'), false, '', '', null),
            'sm' => new PropertySpecification('sm', new PropertyType('string', false, null, '0 20px 0 20px'), false, '', '', null),
        ];
        $specs = [
            'Ct:Grid:Container' => ContentSystemElementTypeSpecificationBuilder::create('Ct:Grid:Container', 'Grid Container')
                ->declared('padding', ['string', 'object'], properties: $nestedProperties)
                ->build(),
        ];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        static::assertSame(
            ['padding' => ['xs' => '0 20px 0 20px', 'sm' => '0 20px 0 20px']],
            new StoredDefaultProvider()->forType($registry, 'Ct:Grid:Container'),
        );
    }

    #[TestDox('keeps translatable defaults in their stored language-map shape')]
    public function testForTypeKeepsTranslatableStoredDefaultShape(): void
    {
        $specs = [
            'Ct:Text' => ContentSystemElementTypeSpecificationBuilder::create('Ct:Text')
                ->primitive('headline', 'string', default: 'Translated', translatable: true)
                ->build(),
        ];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        static::assertSame(
            ['headline' => [Defaults::LANGUAGE_SYSTEM => 'Translated']],
            new StoredDefaultProvider()->forType($registry, 'Ct:Text'),
        );
    }
}
