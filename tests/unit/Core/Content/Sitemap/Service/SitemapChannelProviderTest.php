<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Sitemap\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Sitemap\Service\SitemapChannelProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @internal
 */
#[CoversClass(SitemapChannelProvider::class)]
class SitemapChannelProviderTest extends TestCase
{
    public function testStreamsPlatformChannelsBeforeTenantChannelsWithKeysetPagination(): void
    {
        $platformChannel = $this->createChannel();
        $tenantChannel = $this->createChannel();
        $calls = 0;

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->exactly(4))
            ->method('search')
            ->willReturnCallback(static function (Criteria $criteria, Context $context) use (&$calls, $platformChannel, $tenantChannel): EntitySearchResult {
                static::assertSame(1, $criteria->getLimit());
                static::assertSame(Criteria::TOTAL_COUNT_MODE_NONE, $criteria->getTotalCountMode());
                static::assertCount(1, $criteria->getSorting());

                $filters = $criteria->getFilters();
                if ($calls < 2) {
                    static::assertFalse($context->hasGlobalTenantAccess());
                    static::assertCount($calls, array_filter($filters, static fn (object $filter): bool => $filter instanceof RangeFilter));
                    $channels = $calls === 0 ? new ChannelCollection([$platformChannel]) : new ChannelCollection();
                } else {
                    static::assertTrue($context->hasGlobalTenantAccess());
                    static::assertCount(1, array_filter($filters, static fn (object $filter): bool => $filter instanceof NotEqualsFilter));
                    static::assertCount($calls - 2, array_filter($filters, static fn (object $filter): bool => $filter instanceof RangeFilter));
                    $channels = $calls === 2 ? new ChannelCollection([$tenantChannel]) : new ChannelCollection();
                }

                ++$calls;

                return new EntitySearchResult(\count($channels), $channels, null, $criteria, $context);
            });

        $provider = new SitemapChannelProvider($repository, 1);

        static::assertSame(
            [$platformChannel->getId(), $tenantChannel->getId()],
            array_map(
                static fn (ChannelEntity $channel): string => $channel->getId(),
                iterator_to_array($provider->getChannels(new Criteria()), false),
            ),
        );
    }

    private function createChannel(): ChannelEntity
    {
        $channel = new ChannelEntity();
        $channel->setId(Uuid::randomHex());

        return $channel;
    }
}
