<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Document\Renderer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\LineItemFactoryHandler\BlogLineItemFactory;
use Contena\Core\Checkout\Cart\PriceDefinitionFactory;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Checkout\Document\DocumentConfiguration;
use Contena\Core\Checkout\Document\DocumentException;
use Contena\Core\Checkout\Document\Event\StornoOrdersEvent;
use Contena\Core\Checkout\Document\FileGenerator\FileTypes;
use Contena\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Contena\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Contena\Core\Checkout\Document\Renderer\StornoRenderer;
use Contena\Core\Checkout\Document\Service\DocumentGenerator;
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
class StornoRendererTest extends TestCase
{
    use DocumentTrait;
    use SnapshotTesting;

    private ChannelContext $channelContext;

    private Context $context;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    private StornoRenderer $stornoRenderer;

    private CartService $cartService;

    private DocumentGenerator $documentGenerator;

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
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->stornoRenderer = static::getContainer()->get(StornoRenderer::class);
        $this->cartService = static::getContainer()->get(CartService::class);
        $this->documentGenerator = static::getContainer()->get(DocumentGenerator::class);
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

        $cart = $this->generateDemoCart([19]);
        $orderId = $this->cartService->order($cart, $this->channelContext, new RequestDataBag());

        static::getContainer()->get('order.repository')->update([
            [
                'id' => $orderId,
                'orderDateTime' => '2023-11-24T12:00:00+00:00',
            ],
        ], $this->context);

        $invoiceConfig = new DocumentConfiguration();
        $invoiceConfig->setDocumentNumber('1001');

        $operationInvoice = new DocumentGenerateOperation($orderId, FileTypes::PDF, $invoiceConfig->jsonSerialize());
        $result = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$orderId => $operationInvoice], $this->context)->getSuccess()->first();
        static::assertNotNull($result);
        $invoiceId = $result->getId();

        $config = [
            'documentComment' => '<script></script>This is a cancellation invoice.',
            'custom' => [
                'invoiceNumber' => '1001',
            ],
            'itemsPerPage' => 10,
            'displayHeader' => true,
            'displayFooter' => true,
            'displayPrices' => true,
            'displayPageCount' => true,
            'displayLineItems' => true,
            'displayCompanyAddress' => true,
            'displayReturnAddress' => true,
            'companyName' => 'Example Company',
            'documentDate' => '2023-11-24T12:00:00+00:00',
        ];

        $operationHtml = new DocumentGenerateOperation(
            $orderId,
            HtmlRenderer::FILE_EXTENSION,
            $config,
            $invoiceId
        );

        $processedHtmlTemplate = $this->stornoRenderer->render(
            [$orderId => $operationHtml],
            $this->context,
            new DocumentRendererConfig()
        );

        $renderedHtml = $processedHtmlTemplate->getSuccess()[$orderId];
        static::assertInstanceOf(RenderedDocument::class, $renderedHtml);

        $contentHtml = $renderedHtml->getContent();
        static::assertIsString($contentHtml);

        $this->assertSnapshot('storno_renderer_default', [
            [
                'type' => self::TYPE_HTML,
                'actual' => $contentHtml,
            ],
        ]);
    }

    /**
     * @param array{documentNumber: string, fileTypes: list<string>, custom?: array<string, string>} $additionalConfig
     */
    #[DataProvider('stornoNoteRendererDataProvider')]
    public function testRender(array $additionalConfig, \Closure $assertionCallback): void
    {
        $cart = $this->generateDemoCart([7, 31]);
        $orderId = $this->cartService->order($cart, $this->channelContext, new RequestDataBag());

        $invoiceConfig = new DocumentConfiguration();
        $invoiceConfig->setDocumentNumber('1001');

        $operationInvoice = new DocumentGenerateOperation($orderId, HtmlRenderer::FILE_EXTENSION, $invoiceConfig->jsonSerialize());

        $result = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$orderId => $operationInvoice], $this->context)->getSuccess()->first();
        static::assertNotNull($result);
        $invoiceId = $result->getId();

        $config = [
            'displayLineItems' => true,
            'itemsPerPage' => 10,
            'displayFooter' => true,
            'displayHeader' => true,
        ];

        if ($additionalConfig !== []) {
            $config = array_merge($config, $additionalConfig);
        }

        $operation = new DocumentGenerateOperation(
            $orderId,
            HtmlRenderer::FILE_EXTENSION,
            $config,
            $invoiceId
        );

        $caughtEvent = null;

        EventHookDispatcher::fromContainer(static::getContainer())->on(StornoOrdersEvent::class, static function (StornoOrdersEvent $event) use (&$caughtEvent): void {
            $caughtEvent = $event;
        });

        $processedTemplate = $this->stornoRenderer->render(
            [$orderId => $operation],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertInstanceOf(StornoOrdersEvent::class, $caughtEvent);
        static::assertCount(1, $caughtEvent->getOperations());
        static::assertSame($operation, $caughtEvent->getOperations()[$orderId] ?? null);
        static::assertCount(1, $caughtEvent->getOrders());
        $order = $caughtEvent->getOrders()->get($orderId);
        static::assertNotNull($order);
        static::assertArrayHasKey($orderId, $processedTemplate->getSuccess());
        $rendered = $processedTemplate->getSuccess()[$orderId];
        static::assertStringContainsString('<html lang="en-GB">', $rendered->getContent());
        static::assertStringContainsString('</html>', $rendered->getContent());
        $assertionCallback($rendered);
    }

    public function testRenderWithoutInvoice(): void
    {
        $cart = $this->generateDemoCart([7, 13]);
        $orderId = $this->cartService->order($cart, $this->channelContext, new RequestDataBag());

        $operation = new DocumentGenerateOperation($orderId);

        $processedTemplate = $this->stornoRenderer->render(
            [$orderId => $operation],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertCount(0, $processedTemplate->getSuccess());
        static::assertNotCount(0, $errors = $processedTemplate->getErrors());
        static::assertArrayHasKey($orderId, $errors);
        static::assertInstanceOf(DocumentException::class, $errors[$orderId]);
        static::assertSame(
            "Unable to generate document. Can not generate cancellation invoice document because no invoice document exists. OrderId: $orderId",
            $errors[$orderId]->getMessage()
        );
    }

    /**
     * @return \Generator<string, array{array{documentNumber: string, fileTypes: list<string>, custom?: array<string, string>}, \Closure}>
     */
    public static function stornoNoteRendererDataProvider(): \Generator
    {
        yield 'render storno successfully' => [
            [
                'documentNumber' => '1000',
                'custom' => [
                    'stornoNumber' => '1000',
                    'invoiceNumber' => '1001',
                ],
                'fileTypes' => [HtmlRenderer::FILE_EXTENSION, PdfRenderer::FILE_EXTENSION],
            ],
            static function (?RenderedDocument $rendered = null): void {
                static::assertNotNull($rendered);
                static::assertStringContainsString('Cancellation no. 1000', $rendered->getContent());
                static::assertStringContainsString('Cancellation 1000 for Invoice 1001', $rendered->getContent());
            },
        ];

        yield 'render storno with document number' => [
            [
                'documentNumber' => 'STORNO_9999',
                'fileTypes' => [HtmlRenderer::FILE_EXTENSION, PdfRenderer::FILE_EXTENSION],
            ],
            static function (?RenderedDocument $rendered = null): void {
                static::assertNotNull($rendered);
                static::assertSame('STORNO_9999', $rendered->getNumber());
                static::assertSame('cancellation_invoice_STORNO_9999', $rendered->getName());
            },
        ];
    }

    public function testUsingTheSameOrderVersionIdWithReferenceDocument(): void
    {
        $cart = $this->generateDemoCart([7]);
        $orderId = $this->persistCart($cart);

        $invoiceConfig = new DocumentConfiguration();
        $invoiceConfig->setDocumentNumber('1001');

        $operationInvoice = new DocumentGenerateOperation($orderId, FileTypes::PDF, $invoiceConfig->jsonSerialize());

        $result = $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$orderId => $operationInvoice], $this->context)->getSuccess()->first();
        static::assertNotNull($result);

        $operationStorno = new DocumentGenerateOperation($orderId);

        static::assertSame($operationStorno->getOrderVersionId(), Defaults::LIVE_VERSION);
        static::assertTrue($this->orderVersionExists($orderId, $operationStorno->getOrderVersionId()));

        $this->stornoRenderer->render(
            [$orderId => $operationStorno],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertSame($operationInvoice->getOrderVersionId(), $operationStorno->getOrderVersionId());
        static::assertTrue($this->orderVersionExists($orderId, $operationStorno->getOrderVersionId()));
    }

    /**
     * @param array<int, int> $taxes
     */
    private function generateDemoCart(array $taxes): Cart
    {
        $cart = $this->cartService->createNew('A');

        $blogs = [];

        $factory = new BlogLineItemFactory(new PriceDefinitionFactory());

        $ids = new IdsCollection();

        $lineItems = [];

        foreach ($taxes as $index => $tax) {
            $price = 100.0 + $index;
            $name = 'blog ' . $index;
            $number = 'p' . $index;

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
