<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Event\Subscriber;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\Event\CartMergedEvent;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItem\LineItemCollection;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Event\CartMergedSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\Translation\TranslatorInterface;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class CartMergedSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testMergedHintIsAdded(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->once())
            ->method('trans')
            ->with('checkout.cart-merged-hint')
            ->willReturn('checkout.cart-merged-hint');

        $subscriber = new CartMergedSubscriber($translator, $requestStack);

        $currentContextToken = 'currentToken';
        $currentContext = $this->createChannelContext($currentContextToken, []);

        // Create Guest cart
        $previousCart = new Cart($currentContextToken);

        $blogId1 = $this->createBlog($currentContext->getContext());
        $blogId2 = $this->createBlog($currentContext->getContext());

        $blogLineItem1 = new LineItem($blogId1, LineItem::BLOG_LINE_ITEM_TYPE, $blogId1);
        $blogLineItem2 = new LineItem($blogId2, LineItem::BLOG_LINE_ITEM_TYPE, $blogId2);
        $blogLineItem1->setStackable(true);
        $blogLineItem2->setStackable(true);
        $blogLineItem1->setQuantity(1);
        $blogLineItem2->setQuantity(5);

        $previousCart->addLineItems(new LineItemCollection([$blogLineItem1, $blogLineItem2]));
        $previousCart->markUnmodified();

        $cartMergedEvent = new CartMergedEvent(new Cart('userToken'), $currentContext, $previousCart);

        $subscriber->addCartMergedNoticeFlash($cartMergedEvent);

        static::assertNotCount(0, $infoFlash = $session->getFlashBag()->get('info'));

        static::assertSame('checkout.cart-merged-hint', $infoFlash[0]);
    }

    /**
     * @param array<string, mixed> $channelData
     */
    private function createChannelContext(string $contextToken, array $channelData, ?string $userId = null): ChannelContext
    {
        if ($userId) {
            $channelData[ChannelContextService::CUSTOMER_ID] = $userId;
        }

        return static::getContainer()->get(ChannelContextFactory::class)->create(
            $contextToken,
            TestDefaults::CHANNEL,
            $channelData
        );
    }

    private function createBlog(Context $context): string
    {
        $blogId = Uuid::randomHex();

        $blogNumber = Uuid::randomHex();
        $data = [
            'id' => $blogId,
            'blogNumber' => $blogNumber,
            'stock' => 1,
            'name' => 'Test Blog',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10.99, 'net' => 11.99, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'taxId' => $this->getValidTaxId(),
            'active' => true,
            'visibilities' => [
                ['channelId' => TestDefaults::CHANNEL, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];
        static::getContainer()->get('blog.repository')->create([$data], $context);

        return $blogId;
    }
}
