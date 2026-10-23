<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\DataAbstractionLayer\UpdatedStates;
use Contena\Core\Content\Blog\Events\BlogStatesBeforeChangeEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(BlogStatesBeforeChangeEvent::class)]
class BlogStatesBeforeChangeEventTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testUpdatedStatesCanBeReadAndReplaced(): void
    {
        $context = Context::createDefaultContext();
        $initial = [new UpdatedStates('blog-id', ['physical'], ['digital'])];

        $event = new BlogStatesBeforeChangeEvent($initial, $context);

        static::assertSame($initial, $event->getUpdatedStates());
        static::assertSame($context, $event->getContext());

        $replacement = [new UpdatedStates('other-id', ['digital'], ['physical'])];
        $event->setUpdatedStates($replacement);

        static::assertSame($replacement, $event->getUpdatedStates());
    }
}
