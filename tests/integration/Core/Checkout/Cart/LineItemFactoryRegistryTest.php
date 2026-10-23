<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItemFactoryRegistry;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Content\Blog\Cart\BlogCartProcessor;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class LineItemFactoryRegistryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private LineItemFactoryRegistry $service;

    private ChannelContext $context;

    protected function setUp(): void
    {
        $this->service = static::getContainer()->get(LineItemFactoryRegistry::class);
        $this->context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);
    }

    public function testCreateBlog(): void
    {
        $lineItem = $this->service->create(['type' => 'blog', 'referencedId' => 'test'], $this->context);
        static::assertSame('test', $lineItem->getReferencedId());
        static::assertSame(LineItem::BLOG_LINE_ITEM_TYPE, $lineItem->getType());
        static::assertSame(1, $lineItem->getQuantity());
    }

    public function testCreateBlogWithPriceDefinition(): void
    {
        $this->expectException(CartException::class);

        $this->service->create([
            'type' => 'blog',
            'referencedId' => 'test',
            'priceDefinition' => [
                'price' => 100.0,
                'type' => 'quantity',
                'precision' => 1,
                'taxRules' => [
                    [
                        'taxRate' => 5,
                        'percentage' => 100,
                    ],
                ],
            ],
        ], $this->context);
    }

    public function testCreateBlogWithPriceDefinitionWithPermissions(): void
    {
        $this->context->setPermissions([BlogCartProcessor::ALLOW_BLOG_PRICE_OVERWRITES => true]);

        $lineItem = $this->service->create([
            'type' => 'blog',
            'referencedId' => 'test',
            'priceDefinition' => [
                'price' => 100.0,
                'type' => 'quantity',
                'precision' => 1,
                'taxRules' => [
                    [
                        'taxRate' => 5,
                        'percentage' => 100,
                    ],
                ],
            ],
        ], $this->context);

        static::assertSame('test', $lineItem->getReferencedId());
        static::assertSame(LineItem::BLOG_LINE_ITEM_TYPE, $lineItem->getType());
        static::assertSame(1, $lineItem->getQuantity());
        static::assertInstanceOf(QuantityPriceDefinition::class, $lineItem->getPriceDefinition());
        static::assertSame(100.0, $lineItem->getPriceDefinition()->getPrice());
    }

    public function testUpdateDisabledStackable(): void
    {
        $id = Uuid::randomHex();
        $lineItem = new LineItem($id, LineItem::BLOG_LINE_ITEM_TYPE, Uuid::randomHex(), 1);
        $lineItem->setStackable(false);

        $cart = new Cart('test');
        $cart->add($lineItem);

        $this->expectException(CartException::class);

        $this->service->update($cart, ['id' => $id, 'quantity' => 2], $this->context);
    }

    public function testChangeQuantity(): void
    {
        $id = Uuid::randomHex();
        $lineItem = new LineItem($id, LineItem::BLOG_LINE_ITEM_TYPE, Uuid::randomHex(), 1);
        $lineItem->setStackable(true);

        $cart = new Cart('test');
        $cart->add($lineItem);

        $this->service->update($cart, ['id' => $id, 'quantity' => 2], $this->context);
        static::assertSame(2, $lineItem->getQuantity());
    }

    public function testCreatePromotion(): void
    {
        $lineItem = $this->service->create(['type' => 'promotion', 'referencedId' => 'test'], $this->context);
        static::assertSame('test', $lineItem->getReferencedId());
        static::assertSame(1, $lineItem->getQuantity());
        static::assertSame(LineItem::PROMOTION_LINE_ITEM_TYPE, $lineItem->getType());
    }

    public function testCreateCustomWithoutPermission(): void
    {
        $this->expectException(CartException::class);

        $this->service->create(['type' => 'custom', 'referencedId' => 'test'], $this->context);
    }

    public function testCreateWithPermission(): void
    {
        $this->context->setPermissions([BlogCartProcessor::ALLOW_BLOG_PRICE_OVERWRITES => true]);

        $lineItem = $this->service->create([
            'type' => 'custom',
            'referencedId' => 'test',
        ], $this->context);

        static::assertSame('custom', $lineItem->getType());
        static::assertSame('test', $lineItem->getReferencedId());
    }
}
