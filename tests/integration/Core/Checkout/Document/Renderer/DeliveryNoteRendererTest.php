<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Document\Renderer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\LineItemFactoryHandler\BlogLineItemFactory;
use Contena\Core\Checkout\Cart\PriceDefinitionFactory;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Checkout\Document\Event\DeliveryNoteOrdersEvent;
use Contena\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Contena\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Contena\Core\Checkout\Document\Service\HtmlRenderer;
use Contena\Core\Checkout\Document\Service\PdfRenderer;
use Contena\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Contena\Core\Checkout\DocumentV2\Struct\RenderedDocument;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Adapter\Translation\Translator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Integration\Traits\SnapshotTesting;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Tests\Integration\Core\Checkout\Document\DocumentTrait;

/**
 * @internal
 */
class DeliveryNoteRendererTest extends TestCase
{
    use DocumentTrait;
    use SnapshotTesting;

    private ChannelContext $channelContext;

    private Context $context;

    private DeliveryNoteRenderer $deliveryNoteRenderer;

    private CartService $cartService;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();

        $priceRuleId = Uuid::randomHex();

        $this->channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::CHANNEL,
            [
                ChannelContextService::CUSTOMER_ID => $this->createUser(),
            ]
        );

        $this->channelContext->setRuleIds([$priceRuleId]);
        $this->deliveryNoteRenderer = static::getContainer()->get(DeliveryNoteRenderer::class);
        $this->cartService = static::getContainer()->get(CartService::class);
        $this->blogRepository = static::getContainer()->get('blog.repository');
    }

    protected function tearDown(): void
    {
        static::getContainer()->get(Translator::class)->reset();
        parent::tearDown();
    }

    public function testDocumentSnapshot(): void
    {
        $translator = static::getContainer()->get(Translator::class);
        $translator->injectSettings(
            $this->channelContext->getChannelId(),
            $this->channelContext->getLanguageId(),
            'en-GB',
            $this->channelContext->getContext()
        );

        $cart = $this->generateDemoCart(1);
        $orderId = $this->persistCart($cart);

        static::getContainer()->get('order.repository')->update([
            [
                'id' => $orderId,
                'orderDateTime' => '2023-11-24T12:00:00+00:00',
            ],
        ], $this->context);

        $config = [
            'documentComment' => '<script></script>This is a delivery note.',
            'custom' => [
                'deliveryDate' => '2023-11-24T12:00:00+00:00',
            ],
            'itemsPerPage' => 10,
            'displayHeader' => true,
            'displayFooter' => true,
            'displayPrices' => true,
            'displayPageCount' => true,
            'displayCompanyAddress' => true,
            'displayReturnAddress' => true,
            'companyName' => 'Example Company',
            'documentDate' => '2023-11-24T12:00:00+00:00',
        ];

        $operationHtml = new DocumentGenerateOperation(
            $orderId,
            HtmlRenderer::FILE_EXTENSION,
            $config
        );

        $processedHtmlTemplate = $this->deliveryNoteRenderer->render(
            [$orderId => $operationHtml],
            $this->context,
            new DocumentRendererConfig()
        );

        $renderedHtml = $processedHtmlTemplate->getSuccess()[$orderId];
        static::assertInstanceOf(RenderedDocument::class, $renderedHtml);

        $contentHtml = $renderedHtml->getContent();
        static::assertIsString($contentHtml);

        $this->assertSnapshot('delivery_note_renderer_default', [
            [
                'type' => self::TYPE_HTML,
                'actual' => $contentHtml,
            ],
        ]);
    }

    #[DataProvider('deliveryNoteRendererDataProvider')]
    public function testRender(string $deliveryNoteNumber, \Closure $assertionCallback): void
    {
        $cart = $this->generateDemoCart(3);

        $orderId = $this->cartService->order($cart, $this->channelContext, new RequestDataBag());

        $operation = new DocumentGenerateOperation($orderId, HtmlRenderer::FILE_EXTENSION, [
            'documentNumber' => $deliveryNoteNumber,
            'itemsPerPage' => 2,
            'fileTypes' => [PdfRenderer::FILE_EXTENSION, HtmlRenderer::FILE_EXTENSION],
        ]);

        $caughtEvent = null;

        EventHookDispatcher::fromContainer(static::getContainer())->on(DeliveryNoteOrdersEvent::class, static function (DeliveryNoteOrdersEvent $event) use (&$caughtEvent): void {
            $caughtEvent = $event;
        });

        $processedTemplate = $this->deliveryNoteRenderer->render(
            [$orderId => $operation],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertInstanceOf(DeliveryNoteOrdersEvent::class, $caughtEvent);
        static::assertCount(1, $caughtEvent->getOperations());
        static::assertSame($operation, $caughtEvent->getOperations()[$orderId] ?? null);
        static::assertCount(1, $caughtEvent->getOrders());
        static::assertArrayHasKey($orderId, $processedTemplate->getSuccess());
        $rendered = $processedTemplate->getSuccess()[$orderId];
        $order = $caughtEvent->getOrders()->get($orderId);
        static::assertNotNull($order);

        static::assertCount(1, $caughtEvent->getOrders());
        static::assertStringContainsString('<html lang="en-GB">', $rendered->getContent());
        static::assertStringContainsString('</html>', $rendered->getContent());

        $assertionCallback($deliveryNoteNumber, $order->getOrderNumber(), $rendered);
    }

    public static function deliveryNoteRendererDataProvider(): \Generator
    {
        yield 'render delivery_note successfully' => [
            '2000',
            static function (string $deliveryNoteNumber, string $orderNumber, RenderedDocument $rendered): void {
                $html = $rendered->getContent();
                static::assertStringContainsString('<html lang="en-GB">', $html);
                static::assertStringContainsString('</html>', $html);

                static::assertStringContainsString('Delivery note ' . $deliveryNoteNumber, $html);
                static::assertStringContainsString(\sprintf('Delivery note %s for Order %s ', $deliveryNoteNumber, $orderNumber), $html);
            },
        ];

        yield 'render delivery_note with document number' => [
            'DELIVERY_NOTE_9999',
            static function (string $deliveryNoteNumber, string $orderNumber, RenderedDocument $rendered): void {
                static::assertSame('DELIVERY_NOTE_9999', $rendered->getNumber());
                static::assertSame('delivery_note_DELIVERY_NOTE_9999', $rendered->getName());

                static::assertStringContainsString("Delivery note $deliveryNoteNumber for Order $orderNumber", $rendered->getContent());
                static::assertStringContainsString("Delivery note $deliveryNoteNumber for Order $orderNumber", $rendered->getContent());
            },
        ];
    }

    public function testCreatingNewOrderVersionId(): void
    {
        $cart = $this->generateDemoCart(1);
        $orderId = $this->persistCart($cart);

        $operationDelivery = new DocumentGenerateOperation($orderId);

        static::assertSame($operationDelivery->getOrderVersionId(), Defaults::LIVE_VERSION);

        $this->deliveryNoteRenderer->render(
            [$orderId => $operationDelivery],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertNotSame($operationDelivery->getOrderVersionId(), Defaults::LIVE_VERSION);
    }

    private function generateDemoCart(int $blogsCount): Cart
    {
        $cart = $this->cartService->createNew('A');

        $blogs = [];

        $factory = new BlogLineItemFactory(new PriceDefinitionFactory());

        $ids = new IdsCollection();

        $lineItems = [];

        for ($i = 0; $i < $blogsCount; ++$i) {
            $price = 100.0 + $i;
            $name = 'blog ' . $i;
            $number = 'p' . $i;
            $tax = 19;

            $blog = (new BlogBuilder($ids, $number))
                ->price($price)
                ->name($name)
                ->active(true)
                ->tax('test-tax', $tax)
                ->visibility()
                ->build();

            $blogs[] = $blog;

            $lineItems[] = $factory->create(['id' => $ids->get($number), 'referencedId' => $ids->get($number)], $this->channelContext);
            $this->addTaxDataToChannel($this->channelContext, $blog['tax']);
        }

        $this->blogRepository->create($blogs, $this->context);

        return $this->cartService->add($cart, $lineItems, $this->channelContext);
    }
}
