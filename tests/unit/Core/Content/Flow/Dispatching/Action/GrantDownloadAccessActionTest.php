<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadEntity;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Content\Flow\Dispatching\Action\GrantDownloadAccessAction;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Content\Product\State;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Event\OrderAware;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(GrantDownloadAccessAction::class)]
class GrantDownloadAccessActionTest extends TestCase
{
    private GrantDownloadAccessAction $action;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $updatePayload = [];

    protected function setUp(): void
    {
        $orderLineItemDownloadRepository = static::createStub(EntityRepository::class);
        $orderLineItemDownloadRepository->method('update')->willReturnCallback(
            function (array $payload, Context $context): EntityWrittenContainerEvent {
                $this->updatePayload = $payload;

                return new EntityWrittenContainerEvent($context, new NestedEventCollection([]), []);
            }
        );
        $this->action = new GrantDownloadAccessAction($orderLineItemDownloadRepository);

        $this->updatePayload = [];
    }

    public function testGetName(): void
    {
        static::assertSame('action.grant.download.access', $this->action->getName());
    }

    public function testGetRequirements(): void
    {
        static::assertSame([OrderAware::class], $this->action->requirements());
    }


    public static function orderProvider(): \Generator
    {
        yield 'no order found' => [null, []];

        $order = new OrderEntity();

        yield 'order without line items' => [$order, []];

        $order = new OrderEntity();

        $lineItem = new OrderLineItemEntity();
        $lineItem->setGood(true);
        $lineItem->setId(Uuid::randomHex());

        $order->setLineItems(new OrderLineItemCollection([$lineItem]));

        yield 'order without downloadable line items' => [$order, []];

        $order = new OrderEntity();

        $lineItem = new OrderLineItemEntity();
        $lineItem->setId(Uuid::randomHex());
        $lineItem->setGood(true);

        $lineItem->setPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE, ProductDefinition::TYPE_DIGITAL);
        if (!Feature::isActive('v6.8.0.0')) {
            $lineItem->setStates([State::IS_DOWNLOAD]);
        }

        $downloadId = Uuid::randomHex();
        $download = new OrderLineItemDownloadEntity();
        $download->setId($downloadId);

        $lineItem->setDownloads(new OrderLineItemDownloadCollection([$download]));

        $order->setLineItems(new OrderLineItemCollection([$lineItem]));

        yield 'grant access for order with downloadable line items' => [
            $order,
            [
                [
                    'id' => $downloadId,
                    'accessGranted' => true,
                ],
            ],
        ];

        yield 'revoke access for order with downloadable line items' => [
            $order,
            [
                [
                    'id' => $downloadId,
                    'accessGranted' => false,
                ],
            ],
            false,
        ];
    }
}
