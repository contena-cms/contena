<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Event\Listener\PreHydration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Event\Listener\PreHydration\RedistributeExpansionSubscriber;
use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Contena\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Contena\Core\Test\Stub\ContentSystem\EventFactory;

/**
 * @internal
 */
#[CoversClass(RedistributeExpansionSubscriber::class)]
class RedistributeExpansionSubscriberTest extends TestCase
{
    private RedistributeExpansionSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new RedistributeExpansionSubscriber();
    }

    #[TestDox('generates virtual broadcast provider for consumer with redistribute flag')]
    public function testGeneratesVirtualBroadcastProviderForRedistributeConsumer(): void
    {
        $element = ContentElementBuilder::create('section', 'e1')
            ->withConsumer('blog', ContextType::Single, required: false, redistribute: true)
            ->build();

        $event = EventFactory::preHydration([$element]);
        $this->subscriber->__invoke($event);

        $providers = $element->getProvidesContext();
        static::assertArrayHasKey('blog', $providers);
    }

    #[TestDox('does not generate provider when redistribute flag is false')]
    public function testDoesNotGenerateProviderWhenRedistributeIsFalse(): void
    {
        $element = ContentElementBuilder::create('section', 'e1')
            ->withConsumer('blog', ContextType::Single, required: false, redistribute: false)
            ->build();

        $event = EventFactory::preHydration([$element]);
        $this->subscriber->__invoke($event);

        $providers = $element->getProvidesContext();
        static::assertEmpty($providers);
    }

    #[TestDox('expands redistribute recursively into nested elements')]
    public function testExpandsRecursivelyIntoNestedElements(): void
    {
        $child = ContentElementBuilder::create('child', 'c1')
            ->withConsumer('blog', ContextType::Single, required: false, redistribute: true)
            ->build();

        $parent = ContentElementBuilder::create('parent', 'p1')
            ->withConsumer('blog', ContextType::Single, required: false, redistribute: true)
            ->withSlot('default', [$child])
            ->build();

        $event = EventFactory::preHydration([$parent]);
        $this->subscriber->__invoke($event);

        static::assertArrayHasKey('blog', $parent->getProvidesContext());
        static::assertArrayHasKey('blog', $child->getProvidesContext());
    }

    #[TestDox('uses consumer alias as provider key when set')]
    public function testUsesConsumerAliasAsProviderKeyWhenSet(): void
    {
        $element = ContentElementBuilder::create('section', 'e1')
            ->withConsumer('blog', ContextType::Single, required: false, redistribute: true, consumerAlias: 'myBlog')
            ->build();

        $event = EventFactory::preHydration([$element]);
        $this->subscriber->__invoke($event);

        $providers = $element->getProvidesContext();
        static::assertArrayHasKey('myBlog', $providers);
        static::assertArrayNotHasKey('blog', $providers);
    }

    #[TestDox('throws when redistribute consumer has dotted context path')]
    public function testThrowsWhenRedistributeConsumerHasDottedPath(): void
    {
        $element = ContentElementBuilder::create('section', 'e1')
            ->withConsumer('blog.cover', ContextType::Single, required: false, redistribute: true)
            ->build();

        $event = EventFactory::preHydration([$element]);

        $this->expectExceptionObject(ContentSystemException::redistributeWithDottedPath('blog.cover'));

        $this->subscriber->__invoke($event);
    }

    #[TestDox('throws when virtual provider conflicts with existing provider')]
    public function testThrowsWhenVirtualProviderConflictsWithExistingProvider(): void
    {
        $element = ContentElementBuilder::create('section', 'e1')
            ->withConsumer('blog', ContextType::Single, required: false, redistribute: true)
            ->withProvider('blog', BroadcastDistributionConfig::simple())
            ->build();

        $event = EventFactory::preHydration([$element]);

        $this->expectExceptionObject(ContentSystemException::redistributeConflict('blog'));

        $this->subscriber->__invoke($event);
    }

    #[TestDox('throws on property alias collision within the same element')]
    public function testThrowsOnPropertyAliasCollision(): void
    {
        $element = ContentElementBuilder::create('section', 'e1')
            ->withConsumer('blog', ContextType::Single, required: false, propertyAlias: 'data')
            ->withConsumer('category', ContextType::Single, required: false, propertyAlias: 'data')
            ->build();

        $event = EventFactory::preHydration([$element]);

        $this->expectExceptionObject(ContentSystemException::propertyAliasCollision('data', 'blog', 'category'));

        $this->subscriber->__invoke($event);
    }
}
