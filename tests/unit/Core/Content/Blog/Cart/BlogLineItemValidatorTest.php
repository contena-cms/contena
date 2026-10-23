<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartBehavior;
use Contena\Core\Checkout\Cart\LineItem\QuantityInformation;
use Contena\Core\Checkout\Cart\LineItemFactoryHandler\BlogLineItemFactory;
use Contena\Core\Checkout\Cart\PriceDefinitionFactory;
use Contena\Core\Content\Blog\Cart\BlogCartProcessor;
use Contena\Core\Content\Blog\Cart\BlogLineItemValidator;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
#[CoversClass(BlogLineItemValidator::class)]
class BlogLineItemValidatorTest extends TestCase
{
    public function testSkipStockValidation(): void
    {
        $cart = new Cart(Uuid::randomHex());
        $builder = new BlogLineItemFactory(new PriceDefinitionFactory());
        $channelContext = static::createStub(ChannelContext::class);
        $cart->add(
            $builder
                ->create(['id' => 'blog-1', 'referencedId' => 'blog-1'], $channelContext)
                ->setQuantityInformation(
                    (new QuantityInformation())
                        ->setMinPurchase(1)
                        ->setMaxPurchase(1)
                        ->setPurchaseSteps(1)
                )
        );
        $cart->add(
            $builder
                ->create(['id' => 'blog-2', 'referencedId' => 'blog-2'], $channelContext)
                ->setReferencedId('blog-1')
                ->setQuantityInformation(
                    (new QuantityInformation())
                        ->setMinPurchase(1)
                        ->setMaxPurchase(1)
                        ->setPurchaseSteps(1)
                )
        );

        $cart->setBehavior(new CartBehavior([
            BlogCartProcessor::SKIP_BLOG_STOCK_VALIDATION => true,
        ]));

        static::assertCount(0, $cart->getErrors());

        $validator = new BlogLineItemValidator();
        $validator->validate($cart, $cart->getErrors(), $channelContext);

        static::assertCount(0, $cart->getErrors());
    }

    public function testValidateOnDuplicateBlogsAtMaxPurchase(): void
    {
        $cart = new Cart(Uuid::randomHex());
        $builder = new BlogLineItemFactory(new PriceDefinitionFactory());
        $channelContext = static::createStub(ChannelContext::class);
        $cart->add(
            $builder
            ->create(['id' => 'blog-1', 'referencedId' => 'blog-1'], $channelContext)
            ->setQuantityInformation(
                (new QuantityInformation())
                ->setMinPurchase(1)
                ->setMaxPurchase(1)
                ->setPurchaseSteps(1)
            )
        );
        $cart->add(
            $builder
            ->create(['id' => 'blog-2', 'referencedId' => 'blog-2'], $channelContext)
            ->setReferencedId('blog-1')
            ->setQuantityInformation(
                (new QuantityInformation())
                ->setMinPurchase(1)
                ->setMaxPurchase(1)
                ->setPurchaseSteps(1)
            )
        );

        static::assertCount(0, $cart->getErrors());

        $validator = new BlogLineItemValidator();
        $validator->validate($cart, $cart->getErrors(), $channelContext);

        static::assertCount(1, $cart->getErrors());
    }

    public function testValidateOnDuplicateBlogsWithSafeQuantity(): void
    {
        $cart = new Cart(Uuid::randomHex());
        $builder = new BlogLineItemFactory(new PriceDefinitionFactory());
        $channelContext = static::createStub(ChannelContext::class);
        $cart->add(
            $builder
            ->create(['id' => 'blog-1', 'referencedId' => 'blog-1'], $channelContext)
            ->setQuantityInformation(
                (new QuantityInformation())
                ->setMinPurchase(1)
                ->setMaxPurchase(3)
                ->setPurchaseSteps(1)
            )
        );
        $cart->add(
            $builder
            ->create(['id' => 'blog-2', 'referencedId' => 'blog-2'], $channelContext)
            ->setReferencedId('blog-1')
            ->setQuantityInformation(
                (new QuantityInformation())
                ->setMinPurchase(1)
                ->setMaxPurchase(3)
                ->setPurchaseSteps(1)
            )
        );

        static::assertCount(0, $cart->getErrors());

        $validator = new BlogLineItemValidator();
        $validator->validate($cart, $cart->getErrors(), $channelContext);

        static::assertCount(0, $cart->getErrors());
    }

    public function testValidateOnDuplicateBlogsWithoutQuantityInformation(): void
    {
        $cart = new Cart(Uuid::randomHex());
        $builder = new BlogLineItemFactory(new PriceDefinitionFactory());
        $channelContext = static::createStub(ChannelContext::class);
        $cart->add($builder->create(['id' => 'blog-1', 'referencedId' => 'blog-1'], $channelContext));
        $cart->add($builder->create(['id' => 'blog-2', 'referencedId' => 'blog-2'], $channelContext)->setReferencedId('blog-1'));

        static::assertCount(0, $cart->getErrors());

        $validator = new BlogLineItemValidator();
        $validator->validate($cart, $cart->getErrors(), $channelContext);

        static::assertCount(0, $cart->getErrors());
    }
}
