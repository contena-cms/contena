<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataContext;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextResolutionVisitor;
use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Contena\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Contena\Core\Test\Stub\ContentSystem\StubContextStruct;

/**
 * @internal
 */
#[CoversClass(ContextResolutionVisitor::class)]
class ContextResolutionVisitorTest extends TestCase
{
    private ContextResolutionVisitor $visitor;

    protected function setUp(): void
    {
        $this->visitor = new ContextResolutionVisitor(new ContextPathResolver());
    }

    #[TestDox('distributes broadcast context data to all direct children consumers')]
    public function testDistributesBroadcastContextToAllDirectChildren(): void
    {
        $child1 = ContentElementBuilder::create('child-1', 'c1')
            ->withConsumer('blog', ContextType::Single)
            ->build();

        $child2 = ContentElementBuilder::create('child-2', 'c2')
            ->withConsumer('blog', ContextType::Single)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('blog', 'blog-data')
            ->withProvider('blog', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$child1, $child2])
            ->build();

        $parent->traverse($this->visitor);

        static::assertSame('blog-data', $child1->getProperty('blog'));
        static::assertSame('blog-data', $child2->getProperty('blog'));
    }

    #[TestDox('does not distribute context to children that are not consumers of the key')]
    public function testDoesNotDistributeToNonConsumerChildren(): void
    {
        $nonConsumer = ContentElementBuilder::create('text', 'nc1')->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('blog', 'blog-data')
            ->withProvider('blog', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$nonConsumer])
            ->build();

        $parent->traverse($this->visitor);

        static::assertNull($nonConsumer->getProperty('blog'));
    }

    #[TestDox('applies property alias on consumer, storing data under the alias key')]
    public function testAppliesPropertyAliasOnConsumer(): void
    {
        $child = ContentElementBuilder::create('child', 'c1')
            ->withConsumer('blog', ContextType::Single, propertyAlias: 'myBlog')
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('blog', 'blog-data')
            ->withProvider('blog', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$child])
            ->build();

        $parent->traverse($this->visitor);

        static::assertSame('blog-data', $child->getProperty('myBlog'));
    }

    #[TestDox('resolves nested Struct property via dot notation')]
    public function testResolvesNestedStructPropertyViaDotNotation(): void
    {
        $coverStruct = new StubContextStruct('cover-url');

        $child = ContentElementBuilder::create('child', 'c1')
            ->withConsumer('blog.cover', ContextType::Single)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('blog', $coverStruct)
            ->withProvider('blog', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$child])
            ->build();

        $parent->traverse($this->visitor);

        static::assertSame('cover-url', $child->getProperty('blog.cover'));
    }

    #[TestDox('skips non-matching consumer context keys and sets only the matching one')]
    public function testSkipsNonMatchingConsumerContextKeys(): void
    {
        $child = ContentElementBuilder::create('child', 'c1')
            ->withConsumer('blog', ContextType::Single)
            ->withConsumer('category', ContextType::Single)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('blog', 'blog-data')
            ->withProvider('blog', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$child])
            ->build();

        $parent->traverse($this->visitor);

        static::assertSame('blog-data', $child->getProperty('blog'));
        static::assertNull($child->getProperty('category'));
    }

    #[TestDox('skips distribution when provider data property is null')]
    public function testSkipsDistributionWhenProviderDataIsNull(): void
    {
        $child = ContentElementBuilder::create('child', 'c1')
            ->withConsumer('blog', ContextType::Single)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProvider('blog', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$child])
            ->build();

        $parent->traverse($this->visitor);

        static::assertNull($child->getProperty('blog'));
    }

    #[TestDox('sets null for optional consumer when distributed data is not a Struct')]
    public function testSetsNullForOptionalConsumerWhenPathNotResolvable(): void
    {
        $child = ContentElementBuilder::create('child', 'c1')
            ->withConsumer('blog.cover', ContextType::Single)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('blog', 'not-a-struct')
            ->withProvider('blog', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$child])
            ->build();

        $parent->traverse($this->visitor);

        static::assertNull($child->getProperty('blog.cover'));
    }

    #[TestDox('throws for required consumer when distributed data is not a Struct and path needs resolution')]
    public function testThrowsForRequiredConsumerWhenPathNotResolvable(): void
    {
        $child = ContentElementBuilder::create('child', 'c1')
            ->withConsumer('blog.cover', ContextType::Single, required: true)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withProperty('blog', 'not-a-struct')
            ->withProvider('blog', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$child])
            ->build();

        $this->expectExceptionObject(ContentSystemException::contextPathNotResolvable(
            'blog.cover',
            'c1',
            'Context data is not a Struct instance'
        ));

        $parent->traverse($this->visitor);
    }
}
