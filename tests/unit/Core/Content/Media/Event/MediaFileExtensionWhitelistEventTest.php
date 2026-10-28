<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent;
use Contena\Core\Content\Media\MediaException;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Feature\FeatureException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaFileExtensionWhitelistEvent::class)]
class MediaFileExtensionWhitelistEventTest extends TestCase
{
    public function testGetContextReturnsPassedContext(): void
    {
        $context = Context::createDefaultContext();
        $event = new MediaFileExtensionWhitelistEvent(['jpg'], $context);

        static::assertSame($context, $event->getContext());
    }

    public function testSetWhitelistReplacesTheWhitelist(): void
    {
        $event = new MediaFileExtensionWhitelistEvent(['jpg'], Context::createDefaultContext());

        static::assertSame(['jpg'], $event->getWhitelist());

        $event->setWhitelist(['jpg', 'png']);

        static::assertSame(['jpg', 'png'], $event->getWhitelist());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetNullableContextReturnsContextWhenFeatureInactiveAndContextProvided(): void
    {
        $context = Context::createDefaultContext();
        $event = new MediaFileExtensionWhitelistEvent(['jpg'], $context);

        static::assertSame($context, $event->getNullableContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetNullableContextReturnsNullWithoutContext(): void
    {
        $event = new MediaFileExtensionWhitelistEvent(['jpg']);

        static::assertNull($event->getNullableContext());
    }

    public function testConstructorRequiresContextWhenFeatureActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Not passing $context to ' . MediaFileExtensionWhitelistEvent::class . ' is deprecated and will be required in v6.8.0.'
        ));
        new MediaFileExtensionWhitelistEvent(['jpg']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetContextThrowsWithoutContext(): void
    {
        $event = new MediaFileExtensionWhitelistEvent(['jpg']);

        $this->expectExceptionObject(MediaException::invalidEventData('No context provided. Pass $context to the constructor of ' . MediaFileExtensionWhitelistEvent::class));
        $event->getContext();
    }

    public function testGetNullableContextThrowsWhenFeatureActive(): void
    {
        $event = new MediaFileExtensionWhitelistEvent(['jpg'], Context::createDefaultContext());

        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: getNullableContext() is deprecated, use getContext() instead.'));
        $event->getNullableContext();
    }
}
