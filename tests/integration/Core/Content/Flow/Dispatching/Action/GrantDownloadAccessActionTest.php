<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Flow\Dispatching\Action;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Contena\Core\Checkout\Cart\LineItemFactoryHandler\BlogLineItemFactory;
use Contena\Core\Checkout\Cart\PriceDefinitionFactory;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Channel\AbstractDownloadRoute;
use Contena\Core\System\User\Channel\DownloadRoute;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Content\Flow\Dispatching\BufferedFlowExecutor;
use Contena\Core\Content\Flow\Dispatching\Struct\ActionSequence;
use Contena\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Contena\Core\Content\Flow\FlowCollection;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Contena\Core\Content\MailTemplate\MailTemplateTypes;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Contena\Core\Content\Media\File\FileFetcher;
use Contena\Core\Content\Media\File\FileSaver;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\State;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Contena\Core\Framework\Event\OrderAware;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @internal
 */
class GrantDownloadAccessActionTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private CartService $cartService;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    /**
     * @var EntityRepository<OrderTransactionCollection>
     */
    private EntityRepository $orderTransactionRepository;

    /**
     * @var EntityRepository<FlowCollection>
     */
    private EntityRepository $flowRepository;

    private ChannelContext $channelContext;

    private OrderTransactionStateHandler $orderTransactionStateHandler;

    private AbstractDownloadRoute $downloadRoute;

    private FileFetcher $fileFetcher;

    private FileSaver $fileSaver;

    private string $userId;

    protected function setUp(): void
    {
        $this->cartService = static::getContainer()->get(CartService::class);
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->orderTransactionRepository = static::getContainer()->get('order_transaction.repository');
        $this->flowRepository = static::getContainer()->get('flow.repository');
        $this->userId = $this->createUser();
        $this->channelContext = $this->createDefaultChannelContext();
        $this->orderTransactionStateHandler = static::getContainer()->get(OrderTransactionStateHandler::class);
        $this->downloadRoute = static::getContainer()->get(DownloadRoute::class);
        $this->fileSaver = static::getContainer()->get(FileSaver::class);
        $this->fileFetcher = static::getContainer()->get(FileFetcher::class);
    }

    /**
     * @param array<int, string[]> $blogDownloads
     */
    #[DataProvider('orderCaseProvider')]
    public function testFlowActionRunsOnEnterState(array $blogDownloads): void
    {
        $orderId = $this->placeOrder($blogDownloads);

        $this->assertOrderWithoutGrantedAccess($orderId, $blogDownloads);

        $flowEvent = null;
        $flowListener = function (FlowSendMailActionEvent $event) use (&$flowEvent): void {
            $event = $this->onFlowSendMailActionEvent($event);

            if ($event instanceof FlowSendMailActionEvent) {
                $flowEvent = $event;
            }
        };
        EventHookDispatcher::fromContainer(static::getContainer())->on(FlowSendMailActionEvent::class, $flowListener);

        $mailEvent = null;
        $mailListener = function (MailBeforeSentEvent $event) use (&$mailEvent): void {
            $event = $this->onMailBeforeSentEvent($event);

            if ($event instanceof MailBeforeSentEvent) {
                $mailEvent = $event;
            }
        };
        EventHookDispatcher::fromContainer(static::getContainer())->on(MailBeforeSentEvent::class, $mailListener);

        $this->changeTransactionStateToPaid($orderId);

        $this->assertDispatchedFlowEvent($blogDownloads, $flowEvent);
        $this->assertDispatchedMailEvent($blogDownloads, $mailEvent);

        $this->assertOrderWithGrantedAccess($orderId, $blogDownloads);
    }

    /**
     * @param array<int, string[]> $blogDownloads
     */
    #[DataProvider('orderCaseProvider')]
    public function testFlowActionRunsOnOrderPlaced(array $blogDownloads): void
    {
        $this->cloneDefaultFlow();

        $flowEvent = null;
        $flowListener = function (FlowSendMailActionEvent $event) use (&$flowEvent): void {
            $event = $this->onFlowSendMailActionEvent($event);

            if ($event instanceof FlowSendMailActionEvent) {
                $flowEvent = $event;
            }
        };
        EventHookDispatcher::fromContainer(static::getContainer())->on(FlowSendMailActionEvent::class, $flowListener);

        $mailEvent = null;
        $mailListener = function (MailBeforeSentEvent $event) use (&$mailEvent): void {
            $event = $this->onMailBeforeSentEvent($event);

            if ($event instanceof MailBeforeSentEvent) {
                $mailEvent = $event;
            }
        };
        EventHookDispatcher::fromContainer(static::getContainer())->on(MailBeforeSentEvent::class, $mailListener);

        $orderId = $this->placeOrder($blogDownloads);

        $this->assertDispatchedFlowEvent($blogDownloads, $flowEvent);
        $this->assertDispatchedMailEvent($blogDownloads, $mailEvent);

        $this->assertOrderWithGrantedAccess($orderId, $blogDownloads);
    }

    public static function orderCaseProvider(): \Generator
    {
        yield 'downloadable only' => [
            [
                ['foo.pdf', 'bar.pdf'],
                ['foobar.mp3'],
            ],
        ];
        yield 'mixed cart' => [
            [
                ['baz.pdf'],
                [],
            ],
        ];
    }

    public function onFlowSendMailActionEvent(FlowSendMailActionEvent $event): ?FlowSendMailActionEvent
    {
        $sequence = $event->getStorableFlow()->getFlowState()->currentSequence;

        if ($sequence instanceof ActionSequence && $sequence->action !== 'action.mail.send') {
            return null;
        }

        $event->getDataBag()->add([
            'contentHtml' => str_replace('frontend.account.order.single.download', 'store-api.account.order.single.download', (string) $event->getDataBag()->get('contentHtml')),
            'contentPlain' => str_replace('frontend.account.order.single.download', 'store-api.account.order.single.download', (string) $event->getDataBag()->get('contentPlain')),
        ]);

        return $event;
    }

    public function onMailBeforeSentEvent(MailBeforeSentEvent $event): ?MailBeforeSentEvent
    {
        $data = $event->getData();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mailTemplates.id', $data['templateId']));

        $type = static::getContainer()->get('mail_template_type.repository')->search($criteria, $event->getContext())->getEntities()->first();

        if (!$type instanceof MailTemplateTypeEntity || $type->getTechnicalName() !== MailTemplateTypes::MAILTYPE_DOWNLOADS_DELIVERY) {
            return null;
        }

        return $event;
    }

    /**
     * @param array<int, string[]>|null $blogDownloads
     */
    private function placeOrder(?array $blogDownloads = null): string
    {
        $blogDownloads ??= [[]];

        $cart = $this->cartService->createNew($this->channelContext->getToken());
        $cart = $this->addBlogs($cart, $blogDownloads);

        $orderId = $this->cartService->order($cart, $this->channelContext, new RequestDataBag());
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        return $orderId;
    }

    /**
     * @param array<int, string[]> $blogDownloads
     */
    private function assertOrderWithoutGrantedAccess(string $orderId, array $blogDownloads): string
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems.downloads');
        $criteria->addAssociation('deliveries');

        $order = $this->orderRepository->search($criteria, $this->channelContext->getContext())->getEntities()->first();
        static::assertInstanceOf(OrderEntity::class, $order);

        $lineItems = $order->getLineItems();
        static::assertNotNull($lineItems);
        $lineItems->sortByPosition();
        static::assertCount(\count($blogDownloads), $lineItems);
        static::assertTrue($lineItems->hasLineItemWithType(BlogDefinition::TYPE_DIGITAL));

        if (!Feature::isActive('v6.8.0.0')) {
            static::assertTrue($lineItems->hasLineItemWithState(State::IS_DOWNLOAD));
        }

        foreach ($blogDownloads as $key => $downloadFiles) {
            $lineItem = $lineItems->getAt($key);
            static::assertNotNull($lineItem);
            static::assertNotNull($lineItem->getDownloads());
            static::assertCount(\count($downloadFiles), $lineItem->getDownloads());
            foreach ($lineItem->getDownloads() as $download) {
                static::assertFalse($download->isAccessGranted());

                try {
                    $request = new Request([], [], ['downloadId' => $download->getId(), 'orderId' => $orderId]);
                    $this->downloadRoute->load($request, $this->channelContext);

                    static::fail('Download route returned response without access granted');
                } catch (\Throwable $exception) {
                    static::assertInstanceOf(UserException::class, $exception);
                    static::assertSame(\sprintf('Line item download file with id "%s" not found.', $download->getId()), $exception->getMessage());
                }
            }
        }

        static::assertNotNull($order->getDeliveries());
        if (\in_array([], $blogDownloads, true)) {
            static::assertNotNull($order->getLineItems());
            static::assertTrue($lineItems->hasLineItemWithType(BlogDefinition::TYPE_PHYSICAL));

            if (!Feature::isActive('v6.8.0.0')) {
                static::assertTrue($order->getLineItems()->hasLineItemWithState(State::IS_PHYSICAL));
            }
            static::assertCount(1, $order->getDeliveries());
        } else {
            static::assertCount(0, $order->getDeliveries());
        }

        return $orderId;
    }

    /**
     * @param array<int, string[]> $blogDownloads
     */
    private function assertOrderWithGrantedAccess(string $orderId, array $blogDownloads): void
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems.downloads.media');

        $order = $this->orderRepository->search($criteria, $this->channelContext->getContext())->getEntities()->first();
        static::assertInstanceOf(OrderEntity::class, $order);

        $lineItems = $order->getLineItems();
        static::assertNotNull($lineItems);
        $lineItems->sortByPosition();
        static::assertCount(\count($blogDownloads), $lineItems);
        static::assertTrue($lineItems->hasLineItemWithType(BlogDefinition::TYPE_DIGITAL));

        if (!Feature::isActive('v6.8.0.0')) {
            static::assertTrue($lineItems->hasLineItemWithState(State::IS_DOWNLOAD));
        }

        foreach ($blogDownloads as $key => $downloadFiles) {
            $lineItem = $lineItems->getAt($key);
            static::assertNotNull($lineItem);
            static::assertNotNull($lineItem->getDownloads());
            static::assertCount(\count($downloadFiles), $lineItem->getDownloads());
            foreach ($lineItem->getDownloads() as $download) {
                static::assertTrue($download->isAccessGranted());
                static::assertNotNull($download->getMedia());

                $request = new Request([], [], ['downloadId' => $download->getId(), 'orderId' => $orderId]);
                $response = $this->downloadRoute->load($request, $this->channelContext);
                static::assertInstanceOf(StreamedResponse::class, $response);
                ob_start();
                $response->send();
                $content = ob_get_clean();
                static::assertSame($download->getMediaId(), $content);
            }
        }
    }

    /**
     * @param array<int, string[]> $blogDownloads
     */
    private function assertDispatchedFlowEvent(array $blogDownloads, ?FlowSendMailActionEvent $flowEvent): void
    {
        static::assertInstanceOf(FlowSendMailActionEvent::class, $flowEvent);
        $order = $flowEvent->getStorableFlow()->getData(OrderAware::ORDER);

        static::assertInstanceOf(OrderEntity::class, $order);
        $lineItems = $order->getLineItems();
        static::assertNotNull($lineItems);
        $lineItems->sortByPosition();
        foreach ($blogDownloads as $key => $files) {
            static::assertNotNull($lineItems->getAt($key));
            static::assertNotNull($lineItems->getAt($key)->getDownloads());
            static::assertCount(\count($files), $lineItems->getAt($key)->getDownloads());
            foreach ($lineItems->getAt($key)->getDownloads() as $download) {
                static::assertTrue($download->isAccessGranted());
            }
        }
    }

    /**
     * @param array<int, string[]> $blogDownloads
     */
    private function assertDispatchedMailEvent(array $blogDownloads, ?MailBeforeSentEvent $mailEvent): void
    {
        static::assertInstanceOf(MailBeforeSentEvent::class, $mailEvent);

        $files = array_merge(...$blogDownloads);
        foreach ($files as $file) {
            static::assertIsString($mailEvent->getMessage()->getTextBody());
            static::assertStringContainsString($file, $mailEvent->getMessage()->getTextBody());
            static::assertIsString($mailEvent->getMessage()->getHtmlBody());
            static::assertStringContainsString($file, $mailEvent->getMessage()->getHtmlBody());
        }
    }

    /**
     * @param array<int, string[]> $blogDownloads
     */
    private function addBlogs(Cart $cart, array $blogDownloads): Cart
    {
        $ids = new IdsCollection();
        $taxIds = $this->channelContext->getTaxRules()->getIds();
        $ids->set('t1', (string) array_pop($taxIds));
        $blogs = [];

        foreach ($blogDownloads as $key => $downloadFiles) {
            $blogs[] = (new BlogBuilder($ids, 'blog-' . $key))
                ->price(1.0)
                ->tax('t1')
                ->visibility()
                ->type($downloadFiles === [] ? BlogDefinition::TYPE_PHYSICAL : BlogDefinition::TYPE_DIGITAL)
                ->add('downloads', array_map(static function (string $file): array {
                    [$fileName, $fileExtension] = explode('.', $file);

                    return [
                        'media' => [
                            'id' => Uuid::randomHex(),
                            'fileName' => $fileName,
                            'fileExtension' => $fileExtension,
                            'path' => 'media/' . $fileName . '.' . $fileExtension,
                            'private' => true,
                        ],
                    ];
                }, $downloadFiles))
                ->build();
        }

        $this->channelContext->getContext()->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($blogs): void {
            $this->blogRepository->create($blogs, $context);
            $downloads = array_merge(...array_column($blogs, 'downloads'));

            foreach ($downloads as $download) {
                $media = $download['media'];
                $mediaFile = $this->fileFetcher->fetchBlob($media['id'], $media['fileExtension'], '');
                $this->fileSaver->persistFileToMedia($mediaFile, $media['fileName'], $media['id'], $context);
                $this->fileFetcher->cleanUpTempFile($mediaFile);
            }
        });

        foreach ($ids->prefixed('blog-') as $id) {
            $cart = $this->addBlog($id, 1, $cart, $this->cartService, $this->channelContext);
        }

        return $cart;
    }

    private function changeTransactionStateToPaid(string $orderId): void
    {
        $transaction = $this->orderTransactionRepository
            ->search(
                (new Criteria())
                    ->addFilter(new EqualsFilter('orderId', $orderId))
                    ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING)),
                $this->channelContext->getContext()
            )->getEntities()->first();
        static::assertInstanceOf(OrderTransactionEntity::class, $transaction);

        $this->orderTransactionStateHandler->paid($transaction->getId(), $this->channelContext->getContext());
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();
    }

    private function cloneDefaultFlow(): void
    {
        $flowId = $this->flowRepository
            ->searchIds(
                (new Criteria())
                    ->addFilter(new EqualsFilter('name', 'Deliver ordered blog downloads')),
                $this->channelContext->getContext()
            )->firstId();
        static::assertNotNull($flowId);

        $behavior = new CloneBehavior([
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
        ]);
        $this->flowRepository->clone($flowId, $this->channelContext->getContext(), null, $behavior);
    }

    private function addBlog(string $blogId, int $quantity, Cart $cart, CartService $cartService, ChannelContext $context): Cart
    {
        $factory = new BlogLineItemFactory(new PriceDefinitionFactory());
        $blog = $factory->create(['id' => $blogId, 'referencedId' => $blogId, 'quantity' => $quantity], $context);

        return $cartService->add($cart, $blog, $context);
    }

    private function createDefaultChannelContext(): ChannelContext
    {
        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);

        return $channelContextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL, [ChannelContextService::CUSTOMER_ID => $this->userId]);
    }
}
