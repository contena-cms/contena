<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\DataAbstractionLayer\UpdatedStates;
use Contena\Core\Content\Blog\Events\BlogStatesChangedEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(BlogStatesChangedEvent::class)]
class BlogStatesChangedEventTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testExposesTheUpdatedStatesAndContext(): void
    {
        $context = Context::createDefaultContext();
        $states = [new UpdatedStates('blog-id', ['physical'], ['digital'])];

        $event = new BlogStatesChangedEvent($states, $context);

        static::assertSame($states, $event->getUpdatedStates());
        static::assertSame($context, $event->getContext());
    }
}
