<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Flow\Action;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Content\Flow\Dispatching\FlowFactory;
use Contena\Core\Content\Flow\Dispatching\Storer\OrderStorer;
use Contena\Core\Content\Shared\MailFlow\DataProvider\OrderProvider;
use Contena\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Contena\Core\Framework\App\Flow\Action\AppFlowActionProvider;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Webhook\BusinessEventEncoder;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppFlowActionProvider::class)]
class AppFlowActionProviderTest extends TestCase
{
    public function testGetWebhookPayloadAndHeaders(): void
    {
        $params = [
            ['name' => 'param1', 'type' => 'string', 'value' => '{{ config1 }}'],
            ['name' => 'param2', 'type' => 'string', 'value' => '{{ config2 }} and {{ config3 }}'],
        ];

        $headers = [
            ['name' => 'content-type', 'type' => 'string', 'value' => 'application/json'],
        ];

        $config = [
            'config1' => 'Text 1',
            'config2' => 'Text 2',
            'config3' => 'Text 3',
        ];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(
                ['parameters' => json_encode($params), 'headers' => json_encode($headers)]
            );

        $ids = new IdsCollection();
        $order = new OrderEntity();
        $order->setId($ids->get('orderId'));

        $orderProvider = static::createStub(OrderProvider::class);
        $orderProvider->method('getData')->willReturn($order);

        $context = Generator::generateSalesChannelContext();

        $awareEvent = new CheckoutOrderPlacedEvent($context, $order);

        $orderStorer = new OrderStorer(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            $orderProvider,
        );

        $flow = (new FlowFactory([$orderStorer]))->create($awareEvent);
        $flow->setConfig($config);

        $stringTemplateRender = $this->createMock(StringTemplateRenderer::class);
        $stringTemplateRender->expects($this->exactly(6))
            ->method('render')
            ->willReturnOnConsecutiveCalls(
                'Text 1',
                'Text 2',
                'Text 3',
                'Text 1',
                'Text 2 and Text 3',
                'application/json'
            );

        $appFlowActionProvider = new AppFlowActionProvider(
            $connection,
            static::createStub(BusinessEventEncoder::class),
            $stringTemplateRender
        );

        $webhookData = $appFlowActionProvider->getWebhookPayloadAndHeaders($flow, $ids->get('appFlowActionId'));

        static::assertSame(['param1' => 'Text 1', 'param2' => 'Text 2 and Text 3'], $webhookData['payload']);
        static::assertSame(['content-type' => 'application/json'], $webhookData['headers']);
    }
}
