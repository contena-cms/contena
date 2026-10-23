<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\DataAbstractionLayer\CheapestPrice;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPriceQuantitySelector;
use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPriceUpdater;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Test\Assert\Serialization;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class CheapestPriceUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCheapestPriceFilteredByChannelId(): void
    {
        $ids = new IdsCollection();

        $channelId1 = TestDefaults::CHANNEL;

        $context = Context::createDefaultContext();

        $blogBuilder = (new BlogBuilder($ids, 'testChannelFilter_p1'))
            ->price(100.0)
            ->variant((new BlogBuilder($ids, 'testChannelFilter_v1'))
                ->price(80.0)
                ->visibility($channelId1)
                ->build())
            ->variant((new BlogBuilder($ids, 'testChannelFilter_v2'))
                ->price(90.0)
                ->visibility()
                ->build());

        $blogData = $blogBuilder->build();
        $this->getContainer()->get('blog.repository')->create([$blogData], $context);

        $parentId = $blogData['id'];
        $variantId1 = $blogData['children'][0]['id'];

        $connection = $this->getContainer()->get(Connection::class);
        $quantitySelector = new CheapestPriceQuantitySelector();
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $updater = new CheapestPriceUpdater($connection, $quantitySelector, $eventDispatcher);
        $updater->update([$parentId], $context);

        $cheapestPriceRaw = $connection->fetchOne(
            'SELECT cheapest_price FROM blog WHERE id = UNHEX(:id) AND version_id = UNHEX(:version)',
            [
                'id' => str_replace('-', '', $parentId),
                'version' => str_replace('-', '', $context->getVersionId()),
            ]
        );

        static::assertIsString($cheapestPriceRaw, 'Cheapest price should be stored');
        static::assertNotSame('', $cheapestPriceRaw, 'Cheapest price should be stored');

        $cheapestPrice = Serialization::assertUnserializedInstanceOf(CheapestPriceContainer::class, $cheapestPriceRaw);

        $context = new Context(
            new ChannelApiSource($channelId1)
        );

        $resolvedPrice = $cheapestPrice->resolve($context);
        static::assertNotNull($resolvedPrice);
        static::assertSame($variantId1, $resolvedPrice->getVariantId());

        $price = $resolvedPrice->getPrice()->first();
        static::assertNotNull($price);
        static::assertSame(80.0, $price->getGross());
    }
}
