<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\BaseChannelContext;
use Contena\Core\System\Channel\Context\AbstractBaseChannelContextFactory;
use Contena\Core\System\Channel\Context\CachedBaseChannelContextFactory;
use Contena\Core\System\Channel\Context\CachedChannelContextFactory;
use Contena\Core\System\Channel\Context\InvalidationRaceAwareCache;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

/**
 * @internal
 */
#[CoversClass(CachedBaseChannelContextFactory::class)]
class CachedBaseChannelContextFactoryTest extends TestCase
{
    public function testDoesNotCacheContextWhenTheMarkerWasInvalidatedDuringCreation(): void
    {
        $firstContext = static::createStub(BaseChannelContext::class);
        $secondContext = static::createStub(BaseChannelContext::class);
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $calls = 0;
        $decorated = $this->createMock(AbstractBaseChannelContextFactory::class);
        $decorated->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function () use ($firstContext, $secondContext, $cache, &$calls) {
                if ($calls++ === 0) {
                    $cache->invalidateTags([CachedChannelContextFactory::ALL_TAG]);

                    return $firstContext;
                }

                return $secondContext;
            });

        $factory = new CachedBaseChannelContextFactory($decorated, new InvalidationRaceAwareCache($cache));

        static::assertSame($firstContext, $factory->create('sales-channel-id'));
        static::assertSame($secondContext, $factory->create('sales-channel-id'));
    }
}
