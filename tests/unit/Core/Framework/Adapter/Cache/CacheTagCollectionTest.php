<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CacheTagCollection::class)]
class CacheTagCollectionTest extends TestCase
{
    public function testAddCollectsTagsIntoTheGlobalTrace(): void
    {
        $collection = new CacheTagCollection();

        $collection->add('tag-a');
        $collection->add(['tag-b', 'tag-c']);

        static::assertSame(['tag-a', 'tag-b', 'tag-c'], $collection->getTrace('all'));
    }

    public function testTraceScopesTagsToTheKeyAndReturnsTheClosureResult(): void
    {
        $collection = new CacheTagCollection();

        $result = $collection->trace('route', function () use ($collection) {
            $collection->add('inside-tag');

            return 'closure-result';
        });

        static::assertSame('closure-result', $result);
        static::assertSame(['inside-tag'], $collection->getTrace('route'));
    }

    public function testTagsAddedAfterTheTraceDoNotLeakIntoIt(): void
    {
        $collection = new CacheTagCollection();

        $collection->trace('route', static fn () => null);
        $collection->add('outside-tag');

        static::assertSame(['outside-tag'], $collection->getTrace('all'));
        static::assertSame([], $collection->getTrace('route'));
    }

    public function testResetDropsAllTraces(): void
    {
        $collection = new CacheTagCollection();
        $collection->add('tag-a');

        $collection->reset();

        static::assertSame([], $collection->getTrace('all'));
    }
}
