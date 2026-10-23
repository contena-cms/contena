<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Contena\Core\Content\Media\MediaException;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Feature\FeatureException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(UnusedMediaSearchEvent::class)]
class UnusedMediaSearchEventTest extends TestCase
{
    /**
     * @param array<string> $idsToRemove
     * @param array<string> $expectedIds
     */
    #[DataProvider('removeIdsProvider')]
    public function testRemoveIds(array $idsToRemove, array $expectedIds): void
    {
        $event = new UnusedMediaSearchEvent(['1', '2', '3'], Context::createDefaultContext());
        $event->markAsUsed($idsToRemove);
        static::assertSame($expectedIds, $event->getUnusedIds());
    }

    public function testGetContextReturnsPassedContext(): void
    {
        $context = Context::createDefaultContext();
        $event = new UnusedMediaSearchEvent(['1', '2', '3'], $context);

        static::assertSame($context, $event->getContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetNullableContextReturnsContextWhenFeatureInactiveAndContextProvided(): void
    {
        $context = Context::createDefaultContext();
        $event = new UnusedMediaSearchEvent(['1', '2', '3'], $context);

        static::assertSame($context, $event->getNullableContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetNullableContextReturnsNullWithoutContext(): void
    {
        $event = new UnusedMediaSearchEvent(['1', '2', '3']);

        static::assertNull($event->getNullableContext());
    }

    public function testConstructorRequiresContextWhenFeatureActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Not passing $context to ' . UnusedMediaSearchEvent::class . ' is deprecated and will be required in v6.8.0.'
        ));
        new UnusedMediaSearchEvent(['1', '2', '3']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetContextThrowsWithoutContext(): void
    {
        $event = new UnusedMediaSearchEvent(['1', '2', '3']);

        $this->expectExceptionObject(MediaException::invalidEventData('No context provided. Pass $context to the constructor of ' . UnusedMediaSearchEvent::class));
        $event->getContext();
    }

    public function testGetNullableContextThrowsWhenFeatureActive(): void
    {
        $event = new UnusedMediaSearchEvent(['1', '2', '3'], Context::createDefaultContext());

        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: getNullableContext() is deprecated, use getContext() instead.'));
        $event->getNullableContext();
    }

    /**
     * @return iterable<string, array{idsToRemove: array<string>, expectedIds: array<string>}>
     */
    public static function removeIdsProvider(): iterable
    {
        yield 'remove-last-id' => ['idsToRemove' => ['3'], 'expectedIds' => ['1', '2']];
        yield 'remove-middle-id' => ['idsToRemove' => ['2'], 'expectedIds' => ['1', '3']];
        yield 'remove-multiple' => ['idsToRemove' => ['1', '2'], 'expectedIds' => ['3']];
        yield 'remove-all' => ['idsToRemove' => ['1', '2', '3'], 'expectedIds' => []];
        yield 'remove-non-existing-elem' => ['idsToRemove' => ['4'], 'expectedIds' => ['1', '2', '3']];
    }
}
