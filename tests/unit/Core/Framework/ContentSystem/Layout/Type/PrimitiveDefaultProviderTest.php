<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Layout\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Framework\ContentSystem\Layout\Type\PrimitiveDefaultProvider;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Contena\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;

/**
 * @internal
 */
#[CoversClass(PrimitiveDefaultProvider::class)]
class PrimitiveDefaultProviderTest extends TestCase
{
    #[TestDox('returns only primitives with a non-null default, skipping defaultless primitives and references')]
    public function testForTypeSkipsNullDefaultsAndReferences(): void
    {
        $specs = [
            'CT:Mixed' => ContentSystemElementTypeSpecificationBuilder::create('CT:Mixed')
                ->primitive('withDefault', 'string', default: 'seeded')
                ->primitive('noDefault', 'string', required: true)
                ->reference('blog', ChannelBlogEntity::class)
                ->build(),
        ];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        static::assertSame(['withDefault' => 'seeded'], new PrimitiveDefaultProvider()->forType($registry, 'CT:Mixed'));
    }
}
