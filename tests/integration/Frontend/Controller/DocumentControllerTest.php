<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartBehavior;
use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\Checkout\Cart\LineItemFactoryHandler\BlogLineItemFactory;
use Contena\Core\Checkout\Cart\Order\OrderPersister;
use Contena\Core\Checkout\Cart\PriceDefinitionFactory;
use Contena\Core\Checkout\Cart\Processor;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Checkout\Document\FileGenerator\FileTypes;
use Contena\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Contena\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Contena\Core\Checkout\Document\Service\DocumentConfigLoader;
use Contena\Core\Checkout\Document\Service\DocumentGenerator;
use Contena\Core\Checkout\Document\Service\HtmlRenderer;
use Contena\Core\Checkout\Document\Service\PdfRenderer;
use Contena\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Contena\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileCollection;
use Contena\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Contena\Core\Checkout\DocumentV2\DocumentCollection;
use Contena\Core\Checkout\DocumentV2\DocumentFormat;
use Contena\Core\Checkout\DocumentV2\DocumentType;
use Contena\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Contena\Core\Checkout\DocumentV2\Generation\DocumentGenerator as DocumentV2Generator;
use Contena\Core\Content\Media\MediaService;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Test\TestCaseBase\TaxAddToChannelTestBehaviour;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;
use Contena\Tests\Integration\Core\Checkout\DocumentV2\DocumentV2Trait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class DocumentControllerTest extends TestCase
{
    use DocumentV2Trait;
    use FrontendControllerTestBehaviour;
    use TaxAddToChannelTestBehaviour;

    private const CUSTOMER_EMAIL_ADDRESS = 'user@example.com';

    private const INVALID_FILE_TYPE = 'invalid';

    protected ChannelContext $channelContext;

    protected Context $context;

    private DocumentGenerator $documentGenerator;

    /**
     * @var EntityRepository<DocumentCollection>
     */
    private EntityRepository $documentRepository;

    private DocumentConfigLoader $documentConfigLoader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->documentGenerator = static::getContainer()->get(DocumentGenerator::class);
        $this->documentRepository = static::getContainer()->get('document.repository');
        $this->documentConfigLoader = static::getContainer()->get(DocumentConfigLoader::class);
        // Clear cached config from previous tests to ensure a fresh state
        $this->documentConfigLoader->reset();

        $this->context = Context::createDefaultContext();

        $paymentMethod = $this->getAvailablePaymentMethod();

        $userId = $this->createUser();
        $shippingMethod = $this->getAvailableShippingMethod();
        $this->channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::CHANNEL,
            [
                ChannelContextService::CUSTOMER_ID => $userId,
                ChannelContextService::SHIPPING_METHOD_ID => $shippingMethod->getId(),
                ChannelContextService::PAYMENT_METHOD_ID => $paymentMethod->getId(),
            ]
        );

        $ruleIds = [];
        if ($shippingRuleId = $shippingMethod->getAvailabilityRuleId()) {
            $ruleIds[] = $shippingRuleId;
        }
        if ($paymentRuleId = $paymentMethod->getAvailabilityRuleId()) {
            $ruleIds[] = $paymentRuleId;
        }
        $this->channelContext->setRuleIds($ruleIds);
    }

    public function testUserAbleToViewUploadDocumentWithDeepLinkCode(): void
    {
        $context = Context::createDefaultContext();

        $cart = $this->generateDemoCart(2);
        $orderId = $this->persistCart($cart);
        $fileName = 'invoice';

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, [], null, true);

        $document = $this->documentGenerator->generate(
            InvoiceRenderer::TYPE,
            [$operation->getOrderId() => $operation],
            $context,
        )->getSuccess()->first();

        static::assertNotNull($document);

        $expectedFileContent = 'simple invoice';
        $expectedContentType = 'application/pdf';

        $request = new Request([], [], [], [], [], [], $expectedFileContent);
        $request->query->set('fileName', $fileName);
        $request->server->set('HTTP_CONTENT_TYPE', $expectedContentType);
        $request->server->set('HTTP_CONTENT_LENGTH', (string) mb_strlen($expectedFileContent));
        $request->headers->set('content-length', (string) mb_strlen($expectedFileContent));

        $request->query->set('extension', 'pdf');

        $documentIdStruct = $this->documentGenerator->upload(
            $document->getId(),
            $context,
            $request
        );

        $browser = $this->login(self::CUSTOMER_EMAIL_ADDRESS);

        $browser->request(
            'GET',
            $_SERVER['APP_URL'] . '/account/order/document/' . $documentIdStruct->getId() . '/' . $documentIdStruct->getDeepLinkCode(),
            $this->tokenize('frontend.account.order.single.document', ['fileType' => 'pdf'])
        );

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame($expectedFileContent, $response->getContent());
        static::assertSame($expectedContentType, $response->headers->get('content-type'));

        // User are unable to view the document without valid deepLinkCode
        $browser->request(
            'GET',
            $_SERVER['APP_URL'] . '/account/order/document/' . $documentIdStruct->getId() . '/' . Random::getAlphanumericString(32),
            $this->tokenize('frontend.account.order.single.document', [])
        );

        static::assertSame(404, $browser->getResponse()->getStatusCode());
    }

    /**
     * @param array<string, string> $operationConfig
     */
    #[DataProvider('provideFileTypeParams')]
    public function testDownloadDocument(
        string $documentType,
        string $expectedFileType,
        string $expectedContentType,
        ?string $pathParameter,
        ?string $queryParameter,
        ?string $acceptHeader = null,
        array $operationConfig = [],
    ): void {
        $context = Context::createDefaultContext();

        $cart = $this->generateDemoCart(1);
        $orderId = $this->persistCart($cart);

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, $operationConfig);

        $result = $this->documentGenerator->generate(
            $documentType,
            [$operation->getOrderId() => $operation],
            $context,
        );

        $document = $result->getSuccess()->first();

        static::assertNotNull($document, implode(', ', array_map(
            static fn (\Throwable $e) => $e->getMessage(),
            $result->getErrors(),
        )));

        $browser = $this->login(self::CUSTOMER_EMAIL_ADDRESS);

        $browser->request(
            'GET',
            '/account/order/document/' . $document->getId() . '/' . $document->getDeepLinkCode()
            . ($pathParameter ? '/' . $pathParameter : '')
            . ($queryParameter ? '?fileType=' . $queryParameter : ''),
            [],
            [],
            $acceptHeader ? ['HTTP_ACCEPT' => 'application/pdf'] : [],
        );

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotFalse($response->getContent());
        static::assertNotSame('', $response->getContent());

        $documentEntity = $this->documentRepository->search(new Criteria([$document->getId()]), $context)->getEntities()->first();
        static::assertNotNull($documentEntity);

        $documentConfig = $this->documentConfigLoader->load(InvoiceRenderer::TYPE, TestDefaults::CHANNEL, $context);
        $expectedFilename = $documentConfig->getFilenamePrefix() . $documentEntity->getDocumentNumber() . $documentConfig->getFilenameSuffix();

        static::assertSame(
            'inline; filename=' . $expectedFilename . '.' . $expectedFileType,
            $response->headers->get('content-disposition')
        );
        static::assertStringContainsString(
            $expectedContentType,
            (string) $response->headers->get('content-type')
        );
    }

    public static function provideFileTypeParams(): \Generator
    {
        yield 'with path param pdf' => [
            'documentType' => InvoiceRenderer::TYPE,
            'expectedFileType' => PdfRenderer::FILE_EXTENSION,
            'expectedContentType' => PdfRenderer::FILE_CONTENT_TYPE,
            'pathParameter' => PdfRenderer::FILE_EXTENSION,
            'queryParameter' => null,
        ];

        yield 'with query param html' => [
            'documentType' => InvoiceRenderer::TYPE,
            'expectedFileType' => HtmlRenderer::FILE_EXTENSION,
            'expectedContentType' => HtmlRenderer::FILE_CONTENT_TYPE,
            'pathParameter' => null,
            'queryParameter' => HtmlRenderer::FILE_EXTENSION,
        ];

        yield 'with path param xml' => [
            'documentType' => ZugferdRenderer::TYPE,
            'expectedFileType' => ZugferdRenderer::FILE_EXTENSION,
            'expectedContentType' => ZugferdRenderer::FILE_CONTENT_TYPE,
            'pathParameter' => ZugferdRenderer::FILE_EXTENSION,
            'queryParameter' => null,
            'acceptHeader' => null,
            'operationConfig' => [
                'vatId' => 'DE123456789',
                'bankBic' => 'DEUTDEDBFRA',
                'bankIban' => 'DE89370400440532013000',
                'bankName' => 'Deutsche Bank',
                'taxOffice' => 'Finanzamt Musterstadt',
                'companyUrl' => 'https://www.contena.cn',
                'companyName' => 'Example Company',
                'companyEmail' => 'mail@contena.cn',
                'companyPhone' => '+49 123 4567890',
                'paymentDueDate' => '+30 days',
                'executiveDirector' => 'Max Mustermann',
                'placeOfFulfillment' => 'Musterstadt',
                'placeOfJurisdiction' => 'Musterstadt',
            ],
        ];

        yield 'without params pdf should be returned' => [
            'documentType' => InvoiceRenderer::TYPE,
            'expectedFileType' => PdfRenderer::FILE_EXTENSION,
            'expectedContentType' => PdfRenderer::FILE_CONTENT_TYPE,
            'pathParameter' => null,
            'queryParameter' => null,
        ];

        yield 'Accept header should be ignored and HTML should be returned' => [
            'documentType' => InvoiceRenderer::TYPE,
            'expectedFileType' => HtmlRenderer::FILE_EXTENSION,
            'expectedContentType' => HtmlRenderer::FILE_CONTENT_TYPE,
            'pathParameter' => HtmlRenderer::FILE_EXTENSION,
            'queryParameter' => null,
            'acceptHeader' => 'application/' . PdfRenderer::FILE_EXTENSION,
        ];
    }

    public function testDownloadDocumentShouldThrowExceptionWithInvalidFileTypeParameter(): void
    {
        $context = Context::createDefaultContext();

        $cart = $this->generateDemoCart(1);
        $orderId = $this->persistCart($cart);

        $operation = new DocumentGenerateOperation($orderId);

        $document = $this->documentGenerator->generate(
            InvoiceRenderer::TYPE,
            [$operation->getOrderId() => $operation],
            $context,
        )->getSuccess()->first();

        static::assertNotNull($document);

        $browser = $this->login(self::CUSTOMER_EMAIL_ADDRESS);

        $browser->request(
            'GET',
            '/account/order/document/'
            . $document->getId() . '/'
            . $document->getDeepLinkCode() . '/'
            . self::INVALID_FILE_TYPE,
        );

        $response = $browser->getResponse();

        if (!Feature::isActive('v6.8.0.0')) {
            static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        } else {
            static::assertSame(Response::HTTP_NOT_ACCEPTABLE, $response->getStatusCode());
            static::assertStringContainsString('The requested file type is not supported', (string) $response->getContent());
            static::assertStringContainsString(self::INVALID_FILE_TYPE, (string) $response->getContent());
        }
    }

    public function testDownloadV2DocumentWithFormatName(): void
    {
        $cart = $this->generateDemoCart(1);
        $orderId = $this->persistCart($cart);
        $this->seedDemoBaseConfig(DocumentType::INVOICE->value);

        $document = static::getContainer()->get(DocumentV2Generator::class)->generate(
            new DocumentGenerationRequest(
                $orderId,
                DocumentType::INVOICE,
                [DocumentFormat::ZUGFERD_EMBEDDED_PDF],
                documentNumber: '1000',
            ),
            $this->context,
        );

        /** @var EntityRepository<DocumentFileCollection> $documentFileRepository */
        $documentFileRepository = static::getContainer()->get('document_file.repository');
        $documentFile = $documentFileRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('documentId', $document->getId()))
                ->addFilter(new EqualsFilter('documentFormat', DocumentFormat::ZUGFERD_EMBEDDED_PDF->value)),
            $this->context,
        )->getEntities()->first();

        static::assertInstanceOf(DocumentFileEntity::class, $documentFile);
        static::assertSame(DocumentFormat::ZUGFERD_EMBEDDED_PDF->value, $documentFile->getDocumentFormat());

        $expectedContent = static::getContainer()->get(MediaService::class)->loadFile($documentFile->getMediaId(), $this->context);

        $browser = $this->login(self::CUSTOMER_EMAIL_ADDRESS);
        $browser->request(
            'GET',
            '/account/order/document/' . $document->getId() . '/' . $document->getDeepLinkCode() . '/' . DocumentFormat::ZUGFERD_EMBEDDED_PDF->value,
        );

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame($expectedContent, $response->getContent());
    }

    private function login(string $email): KernelBrowser
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $email,
                'password' => 'contena',
            ])
        );
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        return $browser;
    }

    /**
     * @throws CartException
     * @throws \Exception
     */
    private function generateDemoCart(int $lineItemCount): Cart
    {
        $cart = new Cart('a-b-c');

        $keywords = ['awesome', 'epic', 'high quality'];

        $blogs = [];

        $factory = new BlogLineItemFactory(new PriceDefinitionFactory());

        for ($i = 0; $i < $lineItemCount; ++$i) {
            $id = Uuid::randomHex();

            $price = random_int(100, 200000) / 100.0;

            shuffle($keywords);
            $name = ucfirst(implode(' ', $keywords) . ' blog');

            $blogs[] = [
                'id' => $id,
                'name' => $name,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => $price, 'net' => $price, 'linked' => false],
                ],
                'blogNumber' => Uuid::randomHex(),
                'manufacturer' => ['id' => $id, 'name' => 'test'],
                'tax' => ['id' => $id, 'taxRate' => 19, 'name' => 'test'],
                'stock' => 10,
                'active' => true,
                'visibilities' => [
                    ['channelId' => TestDefaults::CHANNEL, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ];

            $cart->add($factory->create(['id' => $id, 'referencedId' => $id], $this->channelContext));
            $this->addTaxDataToChannel($this->channelContext, end($blogs)['tax']);
        }

        static::getContainer()->get('blog.repository')
            ->create($blogs, Context::createDefaultContext());

        $cart = static::getContainer()->get(Processor::class)->process($cart, $this->channelContext, new CartBehavior());

        return $cart;
    }

    private function persistCart(Cart $cart): string
    {
        $cart = static::getContainer()->get(CartService::class)->recalculate($cart, $this->channelContext);

        return static::getContainer()->get(OrderPersister::class)->persist($cart, $this->channelContext);
    }

    private function createUser(): string
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'userNumber' => '1337',
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'email' => self::CUSTOMER_EMAIL_ADDRESS,
            'password' => TestDefaults::HASHED_PASSWORD,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'channelId' => TestDefaults::CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'userId' => $userId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        static::getContainer()->get('user.repository')->upsert([$user], $this->context);

        return $userId;
    }
}
