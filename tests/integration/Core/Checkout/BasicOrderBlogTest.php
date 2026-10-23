<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\BufferedFlowExecutor;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Test\Integration\Helper\MailEventListener;
use Contena\Core\Test\Integration\Traits\TestShortHands;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class BasicOrderBlogTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TestShortHands;

    public function testBasicOrderFlow(): void
    {
        $blog = (new BlogBuilder(new IdsCollection(), 'p1'))
            ->stock(100)
            ->price(100)
            ->visibility();

        // the blog builder has a helper function to write the blog values to the database, including all dependencies (rules, currencies, properties, etc)
        $blog->write(static::getContainer());

        $context = $this->getContext();
        $context = $this->login($context);

        // now we test that the blog can be added to a users cart
        $cart = $this->addBlogToCart($blog->id, $context);

        $this->assertLineItemInCart($cart, $blog->id);

        $this->assertLineItemUnitPrice($cart, $blog->id, 100);

        $this->assertLineItemTotalPrice($cart, $blog->id, 100);

        $orderId = $this->mailListener(function (MailEventListener $listener) use ($cart, $context) {
            $orderId = $this->order($cart, $context);
            static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

            $listener->assertSent('order_confirmation_mail');

            return $orderId;
        });

        $item = $this->assertBlogInOrder($orderId, $blog->id);

        static::assertSame(100.0, $item->getUnitPrice());

        static::assertSame(100.0, $item->getTotalPrice());

        $this->assertStock($blog->id, 99, 99);
    }
}
