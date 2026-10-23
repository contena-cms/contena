<?php declare(strict_types=1);

namespace Contena\Tests\Bench\Frontend;

use Doctrine\DBAL\Connection;
use PhpBench\Attributes as Bench;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartBehavior;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Content\Blog\Cart\BlogCartProcessor;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Frontend\Controller\CartLineItemController;
use Contena\Tests\Bench\AbstractBenchCase;
use Contena\Tests\Bench\Fixtures;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * @internal - only for performance benchmarks
 */
class CartLineItemControllerBench extends AbstractBenchCase
{
    private const SUBJECT_CUSTOMER = 'user-0';

    private Cart $cart;

    public function setupWithLogin(): void
    {
        $this->ids = clone Fixtures::getIds();
        $this->context = Fixtures::context([
            ChannelContextService::CUSTOMER_ID => $this->ids->get(self::SUBJECT_CUSTOMER),
        ]);
        if (!$this->context->getUserId()) {
            throw new \Exception('User not logged in for bench tests which require it!');
        }

        static::getContainer()->get(Connection::class)->beginTransaction();

        $blog = [
            'id' => $this->ids->get('blog-state-physical-0'),
            'name' => 'Test blog',
            'stock' => 10000,
            'maxPurchase' => 10000,
            'manufacturerId' => $this->ids->get('manufacturer'),
            'blogNumber' => 'TEST-BLOG-0',
            'price' => [
                ['currencyId' => $this->ids->get('currency'), 'gross' => '99.99', 'net' => '84.03', 'linked' => true],
            ],
            'taxId' => $this->ids->get('tax'),
            'categories' => [['id' => $this->ids->get('navigation')]],
            'visibilities' => [
                [
                    'channelId' => $this->context->getChannelId(),
                    'visibility' => 30,
                ],
            ],
        ];

        $this->context->getContext()->scope(Context::SYSTEM_SCOPE, static function (Context $context) use ($blog): void {
            static::getContainer()->get('blog.repository')->create([$blog], $context);
        });

        $this->cart = new Cart($this->context->getToken());
    }

    #[Bench\BeforeMethods(['setupWithLogin'])]
    #[Bench\Assert('mode(variant.time.avg) < 1000ms +/- 20ms')]
    public function bench_add_promotion_and_product(): void
    {
        $blogId = $this->ids->get('blog-state-physical-0');
        $lineItem = (new LineItem(Uuid::randomHex(), LineItem::BLOG_LINE_ITEM_TYPE, $blogId, 5000))
            ->setStackable(true);
        $this->cart->add($lineItem);
        static::getContainer()->get(BlogCartProcessor::class)->collect($this->cart->getData(), $this->cart, $this->context, new CartBehavior());

        // Create a promotion with a set-group of 2 items
        $promotionId = Uuid::randomHex();
        $groupId = Uuid::randomHex();
        $promotion = [
            'id' => $promotionId,
            'name' => 'XXX',
            'code' => $promotionId,
            'active' => true,
            'priority' => 1,
            'useCodes' => true,
            'useSetGroups' => true,
            'channels' => [
                ['channelId' => $this->context->getChannelId(), 'priority' => 1],
            ],
            'setgroups' => [
                [
                    'id' => $groupId,
                    'packagerKey' => 'COUNT',
                    'value' => 2,
                    'sorterKey' => 'PRICE_ASC',
                ],
            ],
            'groupId' => $groupId,
            'discounts' => [
                [
                    'scope' => 'setgroup-1',
                    'type' => 'percentage',
                    'value' => 50,
                    'sorterKey' => 'PRICE_ASC',
                    'considerAdvancedRules' => true,
                    'applierKey' => '2',
                    'pickerKey' => 'VERTICAL',
                    'usageKey' => 'ALL',
                ],
            ],
        ];
        $this->context->getContext()->scope(Context::SYSTEM_SCOPE, static function (Context $context) use ($promotion): void {
            static::getContainer()->get('promotion.repository')->create([$promotion], $context);
        });

        $requestDataBag = new RequestDataBag(['code' => $promotionId]);
        $request = new Request([], $requestDataBag->all());

        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request->setSession($session);

        // Set the session in the RequestStack
        $requestStack = static::getContainer()->get(RequestStack::class);
        $requestStack->push($request);

        static::getContainer()->get(CartLineItemController::class)->addPromotion($this->cart, $request, $this->context);
    }
}
