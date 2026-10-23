<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Document\Renderer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Order\RecalculationService;
use Contena\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Checkout\Document\Event\DocumentTemplateRendererParameterEvent;
use Contena\Core\Checkout\Document\Event\InvoiceOrdersEvent;
use Contena\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Contena\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Contena\Core\Checkout\Document\Renderer\OrderDocumentCriteriaFactory;
use Contena\Core\Checkout\Document\Service\HtmlRenderer;
use Contena\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Contena\Core\Checkout\DocumentV2\Struct\RenderedDocument;
use Contena\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Adapter\Translation\Translator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Contena\Core\Framework\DataAbstractionLayer\VersionManager;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Currency\CurrencyFormatter;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Integration\Traits\SnapshotTesting;
use Contena\Core\Test\TestDefaults;
use Contena\Tests\Integration\Core\Checkout\Document\DocumentTrait;

/**
 * @internal
 */
class InvoiceRendererTest extends TestCase
{
    use DocumentTrait;
    use SnapshotTesting;

    private ChannelContext $channelContext;

    private Context $context;

    private InvoiceRenderer $invoiceRenderer;

    private static string $deLanguageId;

    private static ?\Closure $callback = null;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $priceRuleId = Uuid::randomHex();

        $shippingAddressId = Uuid::randomHex();
        $options = [
            'defaultShippingAddressId' => $shippingAddressId,
        ];

        $additionalAddress = [
            'id' => $shippingAddressId,
            'countryId' => $this->getValidCountryId(),
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Maximilian',
            'lastName' => 'Musterfrau',
            'street' => 'Ebbinghoff 10a',
            'zipcode' => '48624',
            'city' => 'Schöppingen',
        ];

        $this->channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::CHANNEL,
            [
                ChannelContextService::CUSTOMER_ID => $this->createUser($options, $additionalAddress),
            ]
        );

        $this->channelContext->setRuleIds([$priceRuleId]);
        $this->invoiceRenderer = static::getContainer()->get(InvoiceRenderer::class);
        self::$deLanguageId = $this->getDeDeLanguageId();
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

        $cart = $this->generateDemoCartWithTaxes([7]);
        $orderId = $this->persistCart($cart);

        static::getContainer()->get('order.repository')->update([
            [
                'id' => $orderId,
                'orderDateTime' => '2023-11-24T12:00:00+00:00',
            ],
        ], $this->context);

        $config = [
            'documentComment' => '<script></script>This is an invoice.',
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
            $config
        );

        $processedHtmlTemplate = $this->invoiceRenderer->render(
            [$orderId => $operationHtml],
            $this->context,
            new DocumentRendererConfig()
        );

        $renderedHtml = $processedHtmlTemplate->getSuccess()[$orderId];
        static::assertInstanceOf(RenderedDocument::class, $renderedHtml);

        $contentHtml = $renderedHtml->getContent();
        static::assertIsString($contentHtml);

        $this->assertSnapshot('invoice_renderer_default', [
            [
                'type' => self::TYPE_HTML,
                'actual' => $contentHtml,
            ],
        ]);
    }

    /**
     * @param array<int|string, int> $possibleTaxes
     */
    #[DataProvider('invoiceDataProvider')]
    public function testRender(array $possibleTaxes, ?\Closure $beforeRenderHook, \Closure $assertionCallback): void
    {
        $cart = $this->generateDemoCartWithTaxes($possibleTaxes);
        $orderId = $this->persistCart($cart);

        $operationInvoice = new DocumentGenerateOperation($orderId, HtmlRenderer::FILE_EXTENSION);

        $caughtEvent = null;

        EventHookDispatcher::fromContainer(static::getContainer())->on(InvoiceOrdersEvent::class, static function (InvoiceOrdersEvent $event) use (&$caughtEvent): void {
            $caughtEvent = $event;
        });

        if ($beforeRenderHook instanceof \Closure) {
            $beforeRenderHook($operationInvoice, static::getContainer(), EventHookDispatcher::fromContainer(static::getContainer())->on(...));
        }

        $processedTemplate = $this->invoiceRenderer->render(
            [$orderId => $operationInvoice],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertInstanceOf(InvoiceOrdersEvent::class, $caughtEvent);
        static::assertCount(1, $caughtEvent->getOperations());
        static::assertSame($operationInvoice, $caughtEvent->getOperations()[$orderId] ?? null);
        static::assertCount(1, $caughtEvent->getOrders());
        $order = $caughtEvent->getOrders()->get($orderId);
        static::assertNotNull($order);

        if ($processedTemplate->getSuccess() !== []) {
            static::assertArrayHasKey($orderId, $processedTemplate->getSuccess());

            $rendered = $processedTemplate->getSuccess()[$orderId];

            static::assertInstanceOf(OrderLineItemCollection::class, $lineItems = $order->getLineItems());
            static::assertInstanceOf(OrderLineItemEntity::class, $firstLineItem = $lineItems->first());
            static::assertInstanceOf(OrderLineItemEntity::class, $lastLineItem = $lineItems->last());
            static::assertStringContainsString($firstLineItem->getLabel(), $rendered->getContent());
            static::assertStringContainsString($lastLineItem->getLabel(), $rendered->getContent());

            $assertionCallback($rendered, $order, static::getContainer());
        } else {
            $assertionCallback($order->getId(), $processedTemplate->getErrors());
        }
    }

    public static function invoiceDataProvider(): \Generator
    {
        $documentDate = (new \DateTime());

        yield 'render with default language' => [
            [7],
            static function (DocumentGenerateOperation $operation, ContainerInterface $container) use ($documentDate): void {
                $operation->assign([
                    'config' => [
                        'displayHeader' => true,
                        'documentDate' => $documentDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                        'displayLineItems' => true,
                    ],
                ]);
            },
            static function (RenderedDocument $rendered, OrderEntity $order, ContainerInterface $container) use ($documentDate): void {
                static::assertNotNull($order->getCurrency());

                static::assertStringContainsString(
                    $container->get(CurrencyFormatter::class)->formatCurrencyByLanguage(
                        $order->getAmountTotal(),
                        $order->getCurrency()->getIsoCode(),
                        Context::createDefaultContext()->getLanguageId(),
                        Context::createDefaultContext(),
                    ),
                    $rendered->getContent()
                );

                static::assertNotNull($order->getLanguage());
                static::assertNotNull($locale = $order->getLanguage()->getLocale());
                $formatter = new \IntlDateFormatter($locale->getCode(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);
                $formattedDate = $formatter->format($documentDate);

                static::assertNotFalse($formattedDate);
                static::assertStringContainsString(
                    \sprintf('Date %s', $formattedDate),
                    $rendered->getContent()
                );

                static::assertStringContainsString('<html lang="en-GB">', $rendered->getContent());
                static::assertStringContainsString('</html>', $rendered->getContent());
            },
        ];

        yield 'render with different language' => [
            [7],
            static function (DocumentGenerateOperation $operation, ContainerInterface $container) use ($documentDate): void {
                $container->get('order.repository')->upsert([[
                    'id' => $operation->getOrderId(),
                    'languageId' => self::$deLanguageId,
                ]], Context::createDefaultContext());

                $criteria = OrderDocumentCriteriaFactory::create([$operation->getOrderId()]);
                $order = $container->get('order.repository')->search($criteria, Context::createDefaultContext())->getEntities()->get($operation->getOrderId());
                static::assertInstanceOf(OrderEntity::class, $order);

                $context = clone Context::createDefaultContext();
                $context = $context->assign([
                    'languageIdChain' => array_unique(array_filter([self::$deLanguageId, Context::createDefaultContext()->getLanguageId()])),
                ]);
                $delivery = $order->getDeliveries()?->first();
                static::assertNotNull($delivery);
                $shippingMethod = $delivery->getShippingMethod();
                static::assertNotNull($shippingMethod);

                $container->get('shipping_method.repository')->upsert([[
                    'id' => $shippingMethod->getId(),
                    'name' => 'DE express',
                ]], $context);

                $operation->assign([
                    'config' => [
                        'displayHeader' => true,
                        'documentDate' => $documentDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                        'displayLineItems' => true,
                    ],
                ]);
            },
            static function (RenderedDocument $rendered, OrderEntity $order, ContainerInterface $container) use ($documentDate): void {
                static::assertNotNull($order->getCurrency());

                static::assertStringContainsString(
                    preg_replace('/\xc2\xa0/', ' ', $container->get(CurrencyFormatter::class)->formatCurrencyByLanguage(
                        $order->getAmountTotal(),
                        $order->getCurrency()->getIsoCode(),
                        self::$deLanguageId,
                        Context::createDefaultContext(),
                    )) ?? '',
                    preg_replace('/\xc2\xa0/', ' ', $rendered->getContent()) ?? ''
                );
                static::assertStringContainsString('DE express', preg_replace('/\xc2\xa0/', ' ', $rendered->getContent()) ?? 'DE express');

                static::assertNotNull($order->getLanguage());
                static::assertNotNull($locale = $order->getLanguage()->getLocale());
                $formatter = new \IntlDateFormatter($locale->getCode(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);
                $formattedDate = $formatter->format($documentDate);

                static::assertNotFalse($formattedDate);
                static::assertStringContainsString(
                    \sprintf('Datum %s', $formattedDate),
                    $rendered->getContent()
                );

                static::assertStringContainsString('<html lang="de-DE">', $rendered->getContent());
                static::assertStringContainsString('</html>', $rendered->getContent());
            },
        ];

        yield 'render with syntax error' => [
            [7, 19, 22],
            static function (DocumentGenerateOperation $operation, ContainerInterface $container, \Closure $onEvent): void {
                self::$callback = static function (DocumentTemplateRendererParameterEvent $event): void {
                    throw new \RuntimeException('Errors happened while rendering');
                };

                $onEvent(DocumentTemplateRendererParameterEvent::class, self::$callback);
            },
            static function (string $orderId, array $errors): void {
                static::assertNotNull(self::$callback);
                static::assertNotCount(0, $errors);
                static::assertArrayHasKey($orderId, $errors);

                $error = $errors[$orderId];
                self::assertInstanceOf(\RuntimeException::class, $error);
                static::assertSame(
                    'Errors happened while rendering',
                    $error->getMessage()
                );
            },
        ];

        yield 'render with different taxes' => [
            [7, 19, 22],
            static function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $operation->assign([
                    'config' => [
                        'displayLineItems' => true,
                    ],
                ]);
            },
            static function (RenderedDocument $rendered): void {
                foreach ([7, 19, 22] as $possibleTax) {
                    static::assertStringContainsString(
                        \sprintf('plus %d%% VAT', $possibleTax),
                        $rendered->getContent()
                    );
                }
            },
        ];

        yield 'render with shipping address and displayDivergentDeliveryAddress is true' => [
            [7],
            static function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $orderId = $operation->getOrderId();
                $criteria = OrderDocumentCriteriaFactory::create([$orderId]);
                $order = $container->get('order.repository')->search($criteria, Context::createDefaultContext())->getEntities()->get($orderId);
                static::assertInstanceOf(OrderEntity::class, $order);
                $country = $order->getDeliveries()?->getShippingAddress()->getCountries()->first();
                self::assertNotNull($country);
                $country->setCompanyTax(new TaxFreeConfig(true, Defaults::CURRENCY, 0));

                $container->get('country.repository')->update([[
                    'id' => $country->getId(),
                    'companyTax' => ['enabled' => true, 'currencyId' => Defaults::CURRENCY, 'amount' => 0],
                ]], Context::createDefaultContext());
                $companyPhone = '123123123';
                $vatIds = ['VAT-123123'];

                static::assertNotNull($order->getOrderUser());
                $container->get('user.repository')->update([[
                    'id' => $order->getOrderUser()->getUserId(),
                    'vatIds' => $vatIds,
                ]], Context::createDefaultContext());

                $operation->assign([
                    'config' => [
                        'displayLineItems' => true,
                        'itemsPerPage' => 10,
                        'displayFooter' => true,
                        'displayHeader' => true,
                        'executiveDirector' => 'Max Mustermann',
                        'displayDivergentDeliveryAddress' => true,
                        'companyPhone' => $companyPhone,
                        'displayAdditionalNoteDelivery' => true,
                        'deliveryCountries' => [$country->getId()],
                    ],
                ]);
            },
            static function (RenderedDocument $rendered, OrderEntity $order): void {
                static::assertNotNull($orderDeliveries = $order->getDeliveries());
                $shippingAddress = $orderDeliveries->getShippingAddress()->first();
                static::assertNotNull($shippingAddress);

                $rendered = $rendered->getContent();

                static::assertNotNull($shippingAddress->getZipcode());

                static::assertStringContainsString('Shipping address', $rendered);
                static::assertStringContainsString($shippingAddress->getStreet(), $rendered);
                static::assertStringContainsString($shippingAddress->getCity(), $rendered);
                static::assertStringContainsString($shippingAddress->getFirstName(), $rendered);
                static::assertStringContainsString($shippingAddress->getLastName(), $rendered);
                static::assertStringContainsString($shippingAddress->getZipcode(), $rendered);
                static::assertStringContainsString('123123123', $rendered);
            },
        ];

        yield 'render with displayDivergentDeliveryAddress is false' => [
            [7],
            static function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $orderId = $operation->getOrderId();
                $criteria = OrderDocumentCriteriaFactory::create([$orderId]);

                $order = $container->get('order.repository')
                    ->search($criteria, Context::createDefaultContext())->getEntities()->get($orderId);
                static::assertInstanceOf(OrderEntity::class, $order);

                static::assertNotNull($order->getOrderUser());
                $container->get('user.repository')->update([[
                    'id' => $order->getOrderUser()->getUserId(),
                    'vatIds' => ['VAT-123123'],
                ]], Context::createDefaultContext());

                $operation->assign([
                    'config' => [
                        'displayDivergentDeliveryAddress' => false,
                        'displayLineItems' => true,
                        'displayFooter' => true,
                        'displayHeader' => true,
                    ],
                ]);
            },
            static function (RenderedDocument $rendered, OrderEntity $order): void {
                $rendered = $rendered->getContent();
                static::assertNotNull($orderDeliveries = $order->getDeliveries());
                $shippingAddress = $orderDeliveries->getShippingAddress()->first();
                static::assertInstanceOf(OrderAddressEntity::class, $shippingAddress);
                $country = $shippingAddress->getCountry();
                static::assertNotNull($country);

                static::assertNotNull($country->getName());
                static::assertNotNull($shippingAddress->getZipcode());

                static::assertStringNotContainsString($shippingAddress->getFirstName(), $rendered);
                static::assertStringNotContainsString($shippingAddress->getLastName(), $rendered);
            },
        ];

        yield 'render user VAT-ID with displayUserVatId is checked' => [
            [7],
            static function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $orderId = $operation->getOrderId();
                $criteria = OrderDocumentCriteriaFactory::create([$orderId]);

                $order = $container->get('order.repository')
                    ->search($criteria, Context::createDefaultContext())->getEntities()->get($orderId);
                static::assertInstanceOf(OrderEntity::class, $order);

                static::assertNotNull($order->getOrderUser());
                $container->get('user.repository')->update([[
                    'id' => $order->getOrderUser()->getUserId(),
                    'vatIds' => ['VAT-123123'],
                ]], Context::createDefaultContext());

                $orderAddress = $order->getDeliveries()?->first()?->getShippingOrderAddress();

                static::assertNotNull($orderAddress);
                static::assertNotNull($orderAddress->getCountry());

                $container->get('country.repository')->upsert([[
                    'id' => $orderAddress->getCountry()->getId(),
                    'companyTax' => ['amount' => 0, 'enabled' => true, 'currencyId' => Context::createDefaultContext()->getCurrencyId()],
                ]], Context::createDefaultContext());

                $operation->assign([
                    'config' => [
                        'displayLineItems' => true,
                        'displayHeader' => true,
                        'displayUserVatId' => true,
                        'displayAdditionalNoteDelivery' => false,
                    ],
                ]);
            },
            static function (RenderedDocument $rendered, OrderEntity $order): void {
                static::assertNotNull($order->getAddresses());
                static::assertNotNull($order->getOrderUser());

                $user = $order->getOrderUser()->getUser();
                $rendered = $rendered->getContent();

                static::assertNotNull($user);
                static::assertNotNull($user->getVatIds());

                $vatId = $user->getVatIds()[0];

                static::assertStringContainsString("VAT Reg.No: $vatId", $rendered);
            },
        ];

        yield 'render user VAT-ID with displayUserVatId unchecked' => [
            [7],
            static function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $orderId = $operation->getOrderId();
                $criteria = OrderDocumentCriteriaFactory::create([$orderId]);

                $order = $container->get('order.repository')
                    ->search($criteria, Context::createDefaultContext())->getEntities()->get($orderId);
                static::assertInstanceOf(OrderEntity::class, $order);

                static::assertNotNull($order->getOrderUser());
                $container->get('user.repository')->update([[
                    'id' => $order->getOrderUser()->getUserId(),
                    'vatIds' => ['VAT-123123'],
                ]], Context::createDefaultContext());

                $orderAddress = $order->getDeliveries()?->first()?->getShippingOrderAddress();

                static::assertNotNull($orderAddress);
                static::assertNotNull($orderAddress->getCountry());

                $container->get('country.repository')->upsert([[
                    'id' => $orderAddress->getCountry()->getId(),
                    'companyTax' => ['amount' => 0, 'enabled' => true, 'currencyId' => Context::createDefaultContext()->getCurrencyId()],
                ]], Context::createDefaultContext());

                $operation->assign([
                    'config' => [
                        'displayLineItems' => true,
                        'displayHeader' => true,
                        'displayFooter' => false,
                        'displayUserVatId' => false,
                        'displayAdditionalNoteDelivery' => false,
                    ],
                ]);
            },
            static function (RenderedDocument $rendered, OrderEntity $order): void {
                static::assertNotNull($order->getAddresses());
                static::assertNotNull($order->getOrderUser());

                $user = $order->getOrderUser()->getUser();
                $rendered = $rendered->getContent();

                static::assertNotNull($user);
                static::assertNotNull($user->getVatIds());

                static::assertStringNotContainsString('VAT Reg.No:', $rendered);
            },
        ];

        yield 'render with user VAT-ID is null' => [
            [7],
            static function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $orderId = $operation->getOrderId();
                $criteria = OrderDocumentCriteriaFactory::create([$orderId]);

                $order = $container->get('order.repository')
                    ->search($criteria, Context::createDefaultContext())->getEntities()->get($orderId);
                static::assertInstanceOf(OrderEntity::class, $order);

                static::assertNotNull($order->getOrderUser());
                $container->get('user.repository')->update([[
                    'id' => $order->getOrderUser()->getUserId(),
                    'vatIds' => [],
                ]], Context::createDefaultContext());

                $orderAddress = $order->getDeliveries()?->first()?->getShippingOrderAddress();

                static::assertNotNull($orderAddress);
                static::assertNotNull($orderAddress->getCountry());

                $container->get('country.repository')->upsert([[
                    'id' => $orderAddress->getCountry()->getId(),
                    'companyTax' => ['amount' => 0, 'enabled' => true, 'currencyId' => Context::createDefaultContext()->getCurrencyId()],
                ]], Context::createDefaultContext());

                $operation->assign([
                    'config' => [
                        'displayLineItems' => true,
                        'displayFooter' => false,
                        'displayHeader' => true,
                        'displayUserVatId' => true,
                        'displayAdditionalNoteDelivery' => true,
                    ],
                ]);
            },
            static function (RenderedDocument $rendered, OrderEntity $order): void {
                static::assertNotNull($order->getAddresses());
                static::assertNotNull($order->getOrderUser());

                $user = $order->getOrderUser()->getUser();
                $rendered = $rendered->getContent();

                static::assertNotNull($user);
                static::assertCount(0, $user->getVatIds() ?? []);

                static::assertStringNotContainsString('VAT Reg.No:', $rendered);
            },
        ];

        yield 'render with credit item' => [
            [7],
            static function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $context = Context::createDefaultContext();
                $orderId = $operation->getOrderId();

                $versionId = $container->get('order.repository')->createVersion($orderId, $context, 'DRAFT');
                $versionContext = $context->createWithVersionId($versionId);

                // add credit line item to order
                $creditLineItemId = Uuid::randomHex();
                $creditLineItem = new LineItem(
                    $creditLineItemId,
                    LineItem::CREDIT_LINE_ITEM_TYPE,
                    null,
                    1
                );
                $creditLineItem->setLabel('credit-item-1');
                $creditLineItem->setPriceDefinition(new AbsolutePriceDefinition(-20.0));

                $container->get(RecalculationService::class)->addCustomLineItem(
                    $orderId,
                    $creditLineItem,
                    $versionContext,
                );

                // merge the version changes back into LIVE-ORDER-VERSION
                $container->get(VersionManager::class)
                    ->merge($versionId, WriteContext::createFromContext($context));

                $operation->assign([
                    'config' => [
                        'displayLineItems' => true,
                    ],
                ]);
            },
            static function (RenderedDocument $rendered): void {
                $rendered = $rendered->getContent();
                static::assertStringContainsString('credit-item-1', $rendered);
            },
        ];
    }

    public function testCreateNewOrderVersionId(): void
    {
        $cart = $this->generateDemoCartWithTaxes([7]);
        $orderId = $this->persistCart($cart);

        $operationInvoice = new DocumentGenerateOperation($orderId);

        static::assertSame($operationInvoice->getOrderVersionId(), Defaults::LIVE_VERSION);
        static::assertTrue($this->orderVersionExists($orderId, $operationInvoice->getOrderVersionId()));

        $this->invoiceRenderer->render(
            [$orderId => $operationInvoice],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertNotSame($operationInvoice->getOrderVersionId(), Defaults::LIVE_VERSION);
        static::assertTrue($this->orderVersionExists($orderId, $operationInvoice->getOrderVersionId()));
    }

    #[DataProvider('invoiceDataProviderTestAddressBasedVatDisplay')]
    public function testRenderDocumentUsesExpectedAddressForVatDisplay(
        bool $billingCompanyTaxEnabled,
        bool $billingCountryIsConfigured,
        bool $shippingCompanyTaxEnabled,
        bool $shippingCountryIsEu
    ): void {
        $cart = $this->generateDemoCartWithTaxes([7]);
        $orderId = $this->persistCart($cart);
        $invoice = new DocumentGenerateOperation($orderId, HtmlRenderer::FILE_EXTENSION);

        $criteria = OrderDocumentCriteriaFactory::create([$orderId]);
        $order = static::getContainer()->get('order.repository')
            ->search($criteria, Context::createDefaultContext())->getEntities()->get($orderId);
        static::assertInstanceOf(OrderEntity::class, $order);
        $billingAddressId = $order->getBillingAddressId();
        $orderDelivery = $order->getPrimaryOrderDelivery();
        static::assertNotNull($orderDelivery);
        $shippingAddressId = $orderDelivery->getShippingOrderAddressId();

        $billingCountryId = $this->getCountryIdByIsoCode('DE');
        $shippingCountryId = $this->getCountryIdByIsoCode('NL');

        static::getContainer()->get('order_address.repository')->update([
            [
                'id' => $billingAddressId,
                'countryId' => $billingCountryId,
            ],
            [
                'id' => $shippingAddressId,
                'countryId' => $shippingCountryId,
            ],
        ], Context::createDefaultContext());

        static::getContainer()->get('country.repository')->update([
            [
                'id' => $billingCountryId,
                'companyTax' => [
                    'enabled' => $billingCompanyTaxEnabled,
                    'amount' => 0,
                    'currencyId' => Context::createDefaultContext()->getCurrencyId(),
                ],
            ],
            [
                'id' => $shippingCountryId,
                'isEu' => $shippingCountryIsEu,
                'checkVatIdPattern' => false,
                'companyTax' => [
                    'enabled' => $shippingCompanyTaxEnabled,
                    'amount' => 0,
                    'currencyId' => Context::createDefaultContext()->getCurrencyId(),
                ],
            ],
        ], Context::createDefaultContext());

        $this->upsertBaseConfig([
            'displayAdditionalNoteDelivery' => true,
            'displayUserVatId' => false,
            'displayHeader' => true,
            'displayLineItems' => true,
            'displayPrices' => true,
            'deliveryCountries' => $billingCountryIsConfigured ? [$billingCountryId] : [],
            'fileTypes' => ['pdf', 'html'],
        ], InvoiceRenderer::TYPE);

        static::getContainer()->get('order_customer.repository')->upsert([[
            'id' => $order->getOrderUser()?->getId(),
            'vatIds' => ['NL123456789B01'],
        ]], Context::createDefaultContext());

        static::getContainer()->get('user.repository')->upsert([[
            'id' => $order->getOrderUser()?->getUserId(),
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => ['NL123456789B01'],
        ]], Context::createDefaultContext());

        $rendered = $this->invoiceRenderer->render(
            [$orderId => $invoice],
            $this->context,
            new DocumentRendererConfig()
        );

        $content = $rendered->getSuccess()[$orderId]->getContent();
        $usesShippingAddress = Feature::isActive('v6.8.0.0');
        $shouldDisplayVatId = $usesShippingAddress
            ? $shippingCompanyTaxEnabled && $shippingCountryIsEu
            : $billingCompanyTaxEnabled && $billingCountryIsConfigured;

        if ($shouldDisplayVatId) {
            static::assertStringContainsString('VAT Reg.No: NL123456789B01', $content);
            static::assertStringNotContainsString('Incl. VAT', $content);
        } else {
            static::assertStringNotContainsString('VAT Reg.No: NL123456789B01', $content);
            static::assertStringContainsString('Incl. VAT', $content);
        }
    }

    public static function invoiceDataProviderTestAddressBasedVatDisplay(): \Generator
    {
        yield 'billing address matches, shipping company tax is disabled' => [
            'billingCompanyTaxEnabled' => true,
            'billingCountryIsConfigured' => true,
            'shippingCompanyTaxEnabled' => false,
            'shippingCountryIsEu' => true,
        ];

        yield 'billing address matches, shipping country is not in the EU' => [
            'billingCompanyTaxEnabled' => true,
            'billingCountryIsConfigured' => true,
            'shippingCompanyTaxEnabled' => true,
            'shippingCountryIsEu' => false,
        ];

        yield 'billing country is not configured, shipping address matches' => [
            'billingCompanyTaxEnabled' => true,
            'billingCountryIsConfigured' => false,
            'shippingCompanyTaxEnabled' => true,
            'shippingCountryIsEu' => true,
        ];

        yield 'billing company tax is disabled, shipping address matches' => [
            'billingCompanyTaxEnabled' => false,
            'billingCountryIsConfigured' => true,
            'shippingCompanyTaxEnabled' => true,
            'shippingCountryIsEu' => true,
        ];
    }

    #[DataProvider('invoiceDataProviderTestIntraCommunityDeliveryLabel')]
    public function testRenderDocumentDisplayOfIntraCommunityDeliveryLabel(
        string $userType,
        bool $enableIntraCommunityDeliveryLabel,
        bool $enableTaxFreeB2bOption,
        bool $isEuMember,
        bool $validateVat,
        string $vatNumber,
        bool $shouldDisplay
    ): void {
        $cart = $this->generateDemoCartWithTaxes([7]);
        $orderId = $this->persistCart($cart);
        $invoice = new DocumentGenerateOperation($orderId, HtmlRenderer::FILE_EXTENSION);

        $criteria = OrderDocumentCriteriaFactory::create([$orderId]);

        $order = static::getContainer()->get('order.repository')
            ->search($criteria, Context::createDefaultContext())->getEntities()->get($orderId);
        static::assertInstanceOf(OrderEntity::class, $order);

        static::getContainer()->get('user.repository')->update([[
            'id' => $order->getOrderUser()?->getUserId(),
            'accountType' => $userType,
        ]], Context::createDefaultContext());

        $data = [
            'displayAdditionalNoteDelivery' => $enableIntraCommunityDeliveryLabel,
            'displayUserVatId' => false,
            'displayHeader' => true,
            'displayLineItems' => true,
            'displayPrices' => true,
            'fileTypes' => ['pdf', 'html'],
        ];

        $this->upsertBaseConfig($data, InvoiceRenderer::TYPE);

        $orderAddress = $order->getDeliveries()?->first()?->getShippingOrderAddress();

        static::assertNotNull($orderAddress);

        $countryId = $orderAddress->getCountryId();

        $updateData = [
            'id' => $countryId,
            'isEu' => $isEuMember,
            'checkVatIdPattern' => $validateVat,
            'vatIdPattern' => 'DE\d{9}',
        ];

        if ($enableTaxFreeB2bOption) {
            $updateData['companyTax'] = ['amount' => 0, 'enabled' => true, 'currencyId' => Context::createDefaultContext()->getCurrencyId()];
        }

        static::getContainer()->get('country.repository')->upsert([$updateData], Context::createDefaultContext());

        static::getContainer()->get('order_customer.repository')->upsert([
            [
                'id' => $order->getOrderUser()?->getId(),
                'vatIds' => [$vatNumber],
            ],
        ], Context::createDefaultContext());

        static::getContainer()->get('user.repository')->upsert([
            [
                'id' => $order->getOrderUser()?->getUserId(),
                'vatIds' => [$vatNumber],
            ],
        ], Context::createDefaultContext());

        $rendered = $this->invoiceRenderer->render(
            [$orderId => $invoice],
            $this->context,
            new DocumentRendererConfig()
        );

        $data = $rendered->getSuccess();
        static::assertNotCount(0, $data);

        if ($shouldDisplay) {
            static::assertStringContainsString('Intra-community delivery (EU)', $data[$orderId]->getContent());
            if (Feature::isActive('v6.8.0.0')) {
                static::assertStringContainsString("VAT Reg.No: $vatNumber", $data[$orderId]->getContent());
                static::assertStringNotContainsString('Incl. VAT', $data[$orderId]->getContent());
            } else {
                static::assertStringNotContainsString('VAT Reg.No:', $data[$orderId]->getContent());
                static::assertStringContainsString('Incl. VAT', $data[$orderId]->getContent());
            }
        } else {
            static::assertStringNotContainsString('Intra-community delivery (EU)', $data[$orderId]->getContent());
            static::assertStringNotContainsString('VAT Reg.No:', $data[$orderId]->getContent());
            static::assertStringContainsString('Incl. VAT', $data[$orderId]->getContent());
        }
    }

    public static function invoiceDataProviderTestIntraCommunityDeliveryLabel(): \Generator
    {
        yield 'should not be displayed because the option is disabled' => [
            'userType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'enableIntraCommunityDeliveryLabel' => false,
            'enableTaxFreeB2bOption' => true,
            'isEuMember' => true,
            'validateVat' => false,
            'vatNumber' => 'DE123456789',
            'shouldDisplay' => false,
        ];

        yield 'should be displayed because all necessary options are set' => [
            'userType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'enableIntraCommunityDeliveryLabel' => true,
            'enableTaxFreeB2bOption' => true,
            'isEuMember' => true,
            'validateVat' => false,
            'vatNumber' => 'DE123456789',
            'shouldDisplay' => true,
        ];

        yield 'should not be displayed because user account is no B2B account' => [
            'userType' => UserEntity::ACCOUNT_TYPE_PRIVATE,
            'enableIntraCommunityDeliveryLabel' => true,
            'enableTaxFreeB2bOption' => true,
            'isEuMember' => true,
            'validateVat' => false,
            'vatNumber' => 'DE123456789',
            'shouldDisplay' => false,
        ];

        yield 'shall not be displayed cause user shipping country is not in "member country" list' => [
            'userType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'enableIntraCommunityDeliveryLabel' => true,
            'enableTaxFreeB2bOption' => true,
            'isEuMember' => false,
            'validateVat' => false,
            'vatNumber' => 'DE123456789',
            'shouldDisplay' => false,
        ];

        yield 'should be displayed because VAT number is valid' => [
            'userType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'enableIntraCommunityDeliveryLabel' => true,
            'enableTaxFreeB2bOption' => true,
            'isEuMember' => true,
            'validateVat' => true,
            'vatNumber' => 'DE123456789',
            'shouldDisplay' => true,
        ];

        yield 'should not be displayed because VAT number is invalid' => [
            'userType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'enableIntraCommunityDeliveryLabel' => true,
            'enableTaxFreeB2bOption' => true,
            'isEuMember' => true,
            'validateVat' => true,
            'vatNumber' => 'invalid',
            'shouldDisplay' => false,
        ];
    }
}
