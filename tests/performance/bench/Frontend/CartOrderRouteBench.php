<?php declare(strict_types=1);

namespace Contena\Tests\Bench\Frontend;

use Doctrine\DBAL\Connection;
use PhpBench\Attributes as Bench;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartBehavior;
use Contena\Core\Checkout\Cart\Channel\CartOrderRoute;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Content\Blog\Cart\BlogCartProcessor;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\Context\ChannelContextService;

/**
 * @internal - only for performance benchmarks
 */
class CartOrderRouteBench extends AbstractBenchCase
{
    private const SUBJECT_CUSTOMER = 'user-0';
    private const CART_ITEMS_COUNT = 10;

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

        $baseBlog = [
            'name' => 'Test blog',
            'stock' => 10,
            'manufacturerId' => $this->ids->get('manufacturer'),
            'price' => [
                ['currencyId' => $this->ids->get('currency'), 'gross' => '99.99', 'net' => '84.03', 'linked' => true],
            ],
            'taxId' => $this->ids->get('tax'),
            'categories' => [['id' => $this->ids->get('navigation')]],
            'visibilities' => [
                [
                    'channelId' => $this->ids->get('sales-channel'),
                    'visibility' => 30,
                ],
            ],
        ];

        $blogPayload = [];

        for ($i = 0; $i < self::CART_ITEMS_COUNT; ++$i) {
            $blogPayload[] = array_merge($baseBlog, [
                'id' => $this->ids->get('blog-state-physical-' . $i),
                'blogNumber' => $this->ids->get('blog-state-physical-' . $i),
            ]);
            $blogPayload[] = array_merge($baseBlog, [
                'id' => $this->ids->get('blog-state-digital-' . $i),
                'blogNumber' => $this->ids->get('blog-state-digital-' . $i),
                'downloads' => [
                    ['media' => ['fileName' => 'foo' . $i . '_1', 'fileExtension' => 'pdf', 'private' => true]],
                    ['media' => ['fileName' => 'foo' . $i . '_2', 'fileExtension' => 'pdf', 'private' => true]],
                    ['media' => ['fileName' => 'foo' . $i . '_3', 'fileExtension' => 'pdf', 'private' => true]],
                ],
            ]);
        }

        $this->context->getContext()->scope(Context::SYSTEM_SCOPE, static function (Context $context) use ($blogPayload): void {
            static::getContainer()->get('blog.repository')->create($blogPayload, $context);
        });

        $this->cart = new Cart($this->context->getToken());

        foreach ($this->ids->prefixed('blog-state-') as $id) {
            $this->cart->getLineItems()->add(new LineItem(Uuid::randomHex(), LineItem::BLOG_LINE_ITEM_TYPE, $id));
        }

        static::getContainer()->get(BlogCartProcessor::class)->collect($this->cart->getData(), $this->cart, $this->context, new CartBehavior());
    }

    #[Bench\BeforeMethods(['setupWithLogin'])]
    #[Bench\Assert('mode(variant.time.avg) < 150ms +/- 20ms')]
    public function bench_order_10_physical_products(): void
    {
        $this->cart->setLineItems($this->cart->getLineItems()->filter(fn (LineItem $lineItem): bool => \in_array($lineItem->getReferencedId(), $this->ids->prefixed('blog-state-physical-'), true)));
        static::getContainer()->get(CartOrderRoute::class)->order($this->cart, $this->context, new RequestDataBag());
    }

    #[Bench\BeforeMethods(['setupWithLogin'])]
    #[Bench\Assert('mode(variant.time.avg) < 170ms +/- 20ms')]
    public function bench_order_10_digital_products(): void
    {
        $this->cart->setLineItems($this->cart->getLineItems()->filter(fn (LineItem $lineItem): bool => \in_array($lineItem->getReferencedId(), $this->ids->prefixed('blog-state-digital-'), true)));
        static::getContainer()->get(CartOrderRoute::class)->order($this->cart, $this->context, new RequestDataBag());
    }
}
