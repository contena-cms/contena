<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Event\Listener\PreHydration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Event\Listener\PreHydration\VirtualRootPreparationSubscriber;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Contena\Core\System\Language\ContentSystem\DataLoader\LanguageLoaderConfig;
use Contena\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Contena\Core\Test\Stub\ContentSystem\EventFactory;

/**
 * @internal
 */
#[CoversClass(VirtualRootPreparationSubscriber::class)]
class VirtualRootPreparationSubscriberTest extends TestCase
{
    private VirtualRootPreparationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new VirtualRootPreparationSubscriber(new VirtualRootWrapper());
    }

    #[TestDox('wraps elements into virtual root when specification has data requirements')]
    public function testWrapsElementsWhenWrappingRequired(): void
    {
        $element = ContentElementBuilder::create('text', 'e1')->build();

        $requirement = new DataRequirement('language', 'language', new LanguageLoaderConfig());
        $event = EventFactory::preHydration([$element], [$requirement]);

        $this->subscriber->__invoke($event);

        static::assertCount(1, $event->elements);
        static::assertSame('__page_context_root__', $event->elements[0]->getId());
    }

    #[TestDox('skips wrapping when specification has no data requirements')]
    public function testSkipsWrappingWhenNotRequired(): void
    {
        $element = ContentElementBuilder::create('text', 'e1')->build();

        $event = EventFactory::preHydration([$element]);

        $this->subscriber->__invoke($event);

        static::assertCount(1, $event->elements);
        static::assertSame($element, $event->elements[0]);
    }
}
