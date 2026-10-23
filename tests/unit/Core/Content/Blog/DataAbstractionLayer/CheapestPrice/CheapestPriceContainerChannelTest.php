<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\DataAbstractionLayer\CheapestPrice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(CheapestPriceContainer::class)]
class CheapestPriceContainerChannelTest extends TestCase
{
    public function testResolveReturnsPriceWithoutStoredChannelIds(): void
    {
        $container = new CheapestPriceContainer([
            'variant1' => [
                'default' => $this->createPrice(100.0, 84.03),
            ],
        ]);

        $cheapestPrice = $container->resolve($this->createChannelContext(Uuid::randomHex()));

        static::assertNotNull($cheapestPrice);
        static::assertSame('variant1', $cheapestPrice->getVariantId());

        $firstPrice = $cheapestPrice->getPrice()->first();
        static::assertNotNull($firstPrice);
        static::assertSame(100.0, $firstPrice->getGross());
    }

    public function testResolveWithChannelFiltering(): void
    {
        $currentChannelId = Uuid::randomHex();
        $otherChannelId = Uuid::randomHex();

        $testData = [
            'variant1' => [
                'default' => $this->createPrice(50.0, 42.02, [$otherChannelId]),
            ],
            'variant2' => [
                'default' => $this->createPrice(100.0, 84.03, [$currentChannelId]),
            ],
        ];

        $container = new CheapestPriceContainer($testData);
        $cheapestPrice = $container->resolve($this->createChannelContext($currentChannelId));

        static::assertNotNull($cheapestPrice);
        static::assertSame('variant2', $cheapestPrice->getVariantId());

        $firstPrice = $cheapestPrice->getPrice()->first();
        static::assertNotNull($firstPrice);
        static::assertSame(100.0, $firstPrice->getGross());
    }

    public function testResolveWithNoMatchingChannel(): void
    {
        $currentChannelId = Uuid::randomHex();
        $otherChannelId = Uuid::randomHex();

        $testData = [
            'variant1' => [
                'default' => $this->createPrice(50.0, 42.02, [$otherChannelId]),
            ],
        ];

        $container = new CheapestPriceContainer($testData);
        $cheapestPrice = $container->resolve($this->createChannelContext($currentChannelId));

        static::assertNull($cheapestPrice);
    }

    /**
     * @param list<string>|null $channelIds null omits the `channel_ids` key entirely,
     *                                           which marks the price as available everywhere
     *
     * @return array<string, mixed>
     */
    private function createPrice(float $gross, float $net, ?array $channelIds = null): array
    {
        $price = [
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => $gross, 'net' => $net, 'linked' => true],
            ],
            'is_ranged' => false,
            'rule_id' => 'default',
            'parent_id' => 'parent1',
            'purchase_unit' => 1.0,
            'reference_unit' => 1.0,
        ];

        if ($channelIds !== null) {
            $price['channel_ids'] = $channelIds;
        }

        return $price;
    }

    private function createChannelContext(string $channelId): Context
    {
        return new Context(
            new ChannelApiSource($channelId),
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM],
            Defaults::LIVE_VERSION,
            1.0,
            true,
            CartPrice::TAX_STATE_GROSS
        );
    }
}
