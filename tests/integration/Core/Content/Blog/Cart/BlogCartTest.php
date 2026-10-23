<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Cart;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 * This test is used as "good" reference integration tests inside our guidelines.
 */
class BlogCartTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TestShortHands;

    /**
     * @param array<string, mixed> $contextOptions
     */
    #[DataProvider('priceInCartProvider')]
    public function testPriceInCart(BlogBuilder $builder, float $expected, array $contextOptions = []): void
    {
        // the blog builder has a helper function to write the blog values to the database, including all dependencies (rules, currencies, properties, etc)
        $builder->write(static::getContainer());

        if (\is_string($contextOptions['currencyId'] ?? null)) {
            static::getContainer()->get('channel_currency.repository')->create([
                [
                    'channelId' => TestDefaults::CHANNEL,
                    'currencyId' => $contextOptions['currencyId'],
                ],
            ], Context::createDefaultContext());
        }

        $context = $this->getContext(Uuid::randomHex(), $contextOptions);

        // `addBlogToCart` is a small generic helper method to create a blog and add it into the cart within as one liner
        $cart = $this->addBlogToCart($builder->id, $context);

        static::assertTrue($cart->has($builder->id));

        $item = $cart->get($builder->id);

        static::assertInstanceOf(LineItem::class, $item);

        static::assertSame($builder->id, $item->getId());

        static::assertInstanceOf(CalculatedPrice::class, $item->getPrice());
        static::assertSame($expected, $item->getPrice()->getTotalPrice());
    }

    public static function priceInCartProvider(): \Generator
    {
        $ids = new IdsCollection();

        // Important hint: You are not allowed to write values within this function, they are not detected by our database
        // transaction behaviour. So they will not be deleted, and you remain artifacts inside the database

        yield 'Test simple price' => [
            (new BlogBuilder($ids, 'example-1'))->price(100)->visibility(),
            100,
        ];

        yield 'Test another price' => [
            (new BlogBuilder($ids, 'example-1'))->price(200)->visibility(),
            200,
        ];

        yield 'Test different currency' => [
            (new BlogBuilder($ids, 'example-1'))
                ->visibility()
                ->price(30)
                ->price(200, 100, 'dollar'),
            200,
            ['currencyId' => $ids->get('dollar')],
        ];
    }
}
