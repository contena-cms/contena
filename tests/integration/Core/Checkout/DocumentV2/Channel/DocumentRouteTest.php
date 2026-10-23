<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\DocumentV2\Channel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\Checkout\Cart\Exception\UserNotLoggedInException;
use Contena\Core\System\User\UserException;
use Contena\Core\Checkout\Document\DocumentException;
use Contena\Core\Checkout\Document\DocumentIdStruct;
use Contena\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Contena\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Contena\Core\Checkout\Document\Service\DocumentConfigLoader;
use Contena\Core\Checkout\Document\Service\DocumentGenerator;
use Contena\Core\Checkout\Document\Service\HtmlRenderer;
use Contena\Core\Checkout\Document\Service\PdfRenderer;
use Contena\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Contena\Core\Checkout\DocumentV2\DocumentFormat;
use Contena\Core\Checkout\DocumentV2\DocumentType;
use Contena\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Contena\Core\Checkout\DocumentV2\Generation\DocumentGenerator as DocumentV2Generator;
use Contena\Core\Checkout\DocumentV2\Channel\DocumentRoute;
use Contena\Core\Checkout\Order\Exception\GuestNotAuthenticatedException;
use Contena\Core\Checkout\Order\Exception\WrongGuestCredentialsException;
use Contena\Core\Checkout\Order\OrderException;
use Contena\Core\Content\Test\Flow\OrderActionTrait;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\HttpException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\RateLimiter\DisableRateLimiterCompilerPass;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Tests\Integration\Core\Checkout\DocumentV2\DocumentV2Trait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Group('store-api')]
class DocumentRouteTest extends TestCase
{
    /*
     * import of two traits that both define login() with different parameters.
     * The conflict is resolved by using insteadof and internal calls inside OrderActionTrait can still use OrderActionTrait::login()
     * With the alias loginBrowser() the ChannelApiTestBehaviour::login() can be called.
     */
    use DocumentV2Trait, OrderActionTrait, ChannelApiTestBehaviour {
        OrderActionTrait::createUser insteadof DocumentV2Trait;
        OrderActionTrait::login insteadof ChannelApiTestBehaviour;
        ChannelApiTestBehaviour::login as loginBrowser;
    }

    private const INVALID_MIME_TYPE = 'invalid/type';

    private const ACCEPT_WILDCARD = '*/*';

    private const SUPPORTED_FILE_FORMATS = [
        PdfRenderer::FILE_EXTENSION => PdfRenderer::FILE_CONTENT_TYPE,
        HtmlRenderer::FILE_EXTENSION => HtmlRenderer::FILE_CONTENT_TYPE,
        ZugferdRenderer::FILE_EXTENSION => ZugferdRenderer::FILE_CONTENT_TYPE,
    ];

    private KernelBrowser $browser;

    private IdsCollection $ids;

    private DocumentGenerator $documentGenerator;

    private static bool $rateLimitedKernelBooted = false;

    public static function setUpBeforeClass(): void
    {
        DisableRateLimiterCompilerPass::disableNoLimit();
    }

    public static function tearDownAfterClass(): void
    {
        DisableRateLimiterCompilerPass::enableNoLimit();
        // shut down only: the next class boots its kernel lazily inside a test context, which
        // recompiles with the rate limiter restored
        KernelLifecycleManager::ensureKernelShutdown();
        self::$rateLimitedKernelBooted = false;
    }

    protected function setUp(): void
    {
        // the rate-limiter pass applies at container compile time, so this class needs a freshly
        // compiled kernel. It is booted here rather than in setUpBeforeClass(): a deprecation
        // triggered during a static-context kernel boot has no TestCase object on the call stack
        // and crashes PHPUnit's event system instead of being recorded
        if (!self::$rateLimitedKernelBooted) {
            KernelLifecycleManager::bootKernel(true, Uuid::randomHex());
            self::$rateLimitedKernelBooted = true;
        }

        $this->context = Context::createDefaultContext();
        $this->ids = new IdsCollection();

        $this->documentGenerator = static::getContainer()->get(DocumentGenerator::class);
        static::getContainer()->get(DocumentConfigLoader::class)->reset();
        static::getContainer()->get('cache.rate_limiter')->clear();

        $this->createUser(null, false, ['id' => $this->ids->get('user')]);
        $this->createUser(null, true, ['id' => $this->ids->get('guest')]);

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
    }

    /**
     * @param array<string, mixed> $requestParameters
     * @param class-string<HttpException>|null $expectedException
     */
    #[DataProvider('documentDownloadRouteDataProvider')]
    public function testDownload(
        string $orderUserId,
        ?string $loggedInUserId,
        array $requestParameters,
        ?bool $withValidDeepLinkCode,
        ?string $expectedException = null,
        ?string $expectedErrorCode = null,
    ): void {
        if (!$this->ids->has($orderUserId)) {
            $this->createUser(null, false, ['id' => $this->ids->get($orderUserId)]);
        }

        if ($loggedInUserId !== null && !$this->ids->has($loggedInUserId)) {
            $this->createUser(null, false, ['id' => $this->ids->get($loggedInUserId)]);
        }

        $this->createOrder($this->ids->get($orderUserId));

        $channelContext = $this->createChannelContext([], [
            'userId' => $loggedInUserId !== null ? $this->ids->get($loggedInUserId) : null,
        ]);

        $operation = new DocumentGenerateOperation($this->ids->get('order'));

        $document = $this->documentGenerator->generate(
            InvoiceRenderer::TYPE,
            [$operation->getOrderId() => $operation],
            Context::createDefaultContext()
        )->getSuccess()->first();

        static::assertInstanceOf(DocumentIdStruct::class, $document);

        $deepLinkCode = '';
        if ($withValidDeepLinkCode !== null) {
            $deepLinkCode = $withValidDeepLinkCode ? $document->getDeepLinkCode() : Uuid::randomHex();
        }

        $request = new Request([], $requestParameters);

        $documentRoute = static::getContainer()->get(DocumentRoute::class);

        try {
            $response = $documentRoute->download(
                $document->getId(),
                $request,
                $channelContext,
                $deepLinkCode
            );
        } catch (HttpException $e) {
            if (!$expectedException) {
                throw $e;
            }

            static::assertInstanceOf($expectedException, $e);
            static::assertSame($expectedErrorCode, $e->getErrorCode());

            return;
        }
        $headers = $response->headers;

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = $response->getContent();
        static::assertNotFalse($content);
        static::assertNotSame('', $content);
        static::assertSame('inline; filename=invoice_1000.pdf', $headers->get('content-disposition'));
        static::assertSame('application/pdf', $headers->get('content-type'));
    }

    public static function documentDownloadRouteDataProvider(): \Generator
    {
        // valid email and zipcode are 'test@example.com' and '48624', see OrderActionTrait

        yield 'logged in guest with valid deep link code' => [
            'orderUserId' => 'guest',
            'loggedInUserId' => 'guest',
            'requestParameters' => [],
            'withValidDeepLinkCode' => true,
        ];

        yield 'logged in guest without deep link code' => [
            'orderUserId' => 'guest',
            'loggedInUserId' => 'guest',
            'requestParameters' => [],
            'withValidDeepLinkCode' => null,
            'expectedException' => UserNotLoggedInException::class,
            'expectedErrorCode' => CartException::CUSTOMER_NOT_LOGGED_IN_CODE,
        ];

        yield 'logged in guest with invalid deep link code' => [
            'orderUserId' => 'guest',
            'loggedInUserId' => 'guest',
            'requestParameters' => [],
            'withValidDeepLinkCode' => false,
            'expectedException' => DocumentException::class,
            'expectedErrorCode' => DocumentException::DOCUMENT_NOT_FOUND,
        ];

        yield 'guest with correct request params and valid deep link code' => [
            'orderUserId' => 'guest',
            'loggedInUserId' => null,
            'requestParameters' => [
                'email' => 'test@example.com',
                'zipcode' => '48624',
            ],
            'withValidDeepLinkCode' => true,
        ];

        yield 'guest without request params' => [
            'orderUserId' => 'guest',
            'loggedInUserId' => null,
            'requestParameters' => [],
            'withValidDeepLinkCode' => true,
            'expectedException' => GuestNotAuthenticatedException::class,
            'expectedErrorCode' => OrderException::CHECKOUT_GUEST_NOT_AUTHENTICATED,
        ];

        yield 'guest with invalid request params' => [
            'orderUserId' => 'guest',
            'loggedInUserId' => null,
            'requestParameters' => [
                'email' => 'invalid',
                'zipcode' => 'invalid',
            ],
            'withValidDeepLinkCode' => true,
            'expectedException' => WrongGuestCredentialsException::class,
            'expectedErrorCode' => OrderException::CHECKOUT_GUEST_WRONG_CREDENTIALS,
        ];

        yield 'guest with invalid request params and invalid deep link code' => [
            'orderUserId' => 'guest',
            'loggedInUserId' => null,
            'requestParameters' => [
                'email' => 'invalid',
                'zipcode' => 'invalid',
            ],
            'withValidDeepLinkCode' => false,
            'expectedException' => DocumentException::class,
            'expectedErrorCode' => DocumentException::DOCUMENT_NOT_FOUND,
        ];

        yield 'guest with correct request params and without deep link code' => [
            'orderUserId' => 'guest',
            'loggedInUserId' => null,
            'requestParameters' => [
                'email' => 'test@example.com',
                'zipcode' => '48624',
            ],
            'withValidDeepLinkCode' => null,
            'expectedException' => UserNotLoggedInException::class,
            'expectedErrorCode' => CartException::CUSTOMER_NOT_LOGGED_IN_CODE,
        ];

        yield 'guest with correct request params and invalid deep link code' => [
            'orderUserId' => 'guest',
            'loggedInUserId' => null,
            'requestParameters' => [
                'email' => 'test@example.com',
                'zipcode' => '48624',
            ],
            'withValidDeepLinkCode' => false,
            'expectedException' => DocumentException::class,
            'expectedErrorCode' => DocumentException::DOCUMENT_NOT_FOUND,
        ];

        yield 'user with valid deep link code' => [
            'orderUserId' => 'user',
            'loggedInUserId' => 'user',
            'requestParameters' => [],
            'withValidDeepLinkCode' => true,
        ];

        yield 'user with invalid deep link code' => [
            'orderUserId' => 'user',
            'loggedInUserId' => 'user',
            'requestParameters' => [],
            'withValidDeepLinkCode' => false,
            'expectedException' => DocumentException::class,
            'expectedErrorCode' => DocumentException::DOCUMENT_NOT_FOUND,
        ];

        yield 'user without deep link code' => [
            'orderUserId' => 'user',
            'loggedInUserId' => 'user',
            'requestParameters' => [],
            'withValidDeepLinkCode' => null,
            'expectedException' => UserNotLoggedInException::class,
            'expectedErrorCode' => CartException::CUSTOMER_NOT_LOGGED_IN_CODE,
        ];

        yield 'different user with valid deep link code' => [
            'orderUserId' => 'user',
            'loggedInUserId' => 'different-user',
            'requestParameters' => [],
            'withValidDeepLinkCode' => null,
            'expectedException' => Feature::isActive('v6.8.0.0') ? UserException::class : UserNotLoggedInException::class,
            'expectedErrorCode' => CartException::CUSTOMER_NOT_LOGGED_IN_CODE,
        ];

        yield 'order by guest but logged in user with valid deep link code' => [
            'orderUserId' => 'guest',
            'loggedInUserId' => 'user',
            'requestParameters' => [],
            'withValidDeepLinkCode' => true,
            'expectedException' => GuestNotAuthenticatedException::class,
            'expectedErrorCode' => OrderException::CHECKOUT_GUEST_NOT_AUTHENTICATED,
        ];

        yield 'order by guest but logged in user with valid deep link code with correct request params' => [
            'orderUserId' => 'guest',
            'loggedInUserId' => 'user',
            'requestParameters' => [
                'email' => 'test@example.com',
                'zipcode' => '48624',
            ],
            'withValidDeepLinkCode' => true,
        ];

        yield 'order by user but guest with with correct request params' => [
            'orderUserId' => 'user',
            'loggedInUserId' => null,
            'requestParameters' => [
                'email' => 'test@example.com',
                'zipcode' => '48624',
            ],
            'withValidDeepLinkCode' => true,
            'expectedException' => Feature::isActive('v6.8.0.0') ? UserException::class : UserNotLoggedInException::class,
            'expectedErrorCode' => CartException::CUSTOMER_NOT_LOGGED_IN_CODE,
        ];

        yield 'order by user but logged in guest' => [
            'orderUserId' => 'user',
            'loggedInUserId' => 'guest',
            'requestParameters' => [],
            'withValidDeepLinkCode' => true,
            'expectedException' => Feature::isActive('v6.8.0.0') ? UserException::class : UserNotLoggedInException::class,
            'expectedErrorCode' => CartException::CUSTOMER_NOT_LOGGED_IN_CODE,
        ];
    }

    #[DataProvider('provideRequestAcceptHeaderValues')]
    public function testDownloadWithMimeTypesInAcceptHeader(
        string $documentType,
        string $expectedFileType,
        string $acceptHeader,
        string $expectedResponseContentType,
    ): void {
        $userId = $this->loginBrowser($this->browser);
        $this->createOrder(
            $userId,
            ['channelId' => $this->ids->get('sales-channel')]
        );

        $operation = new DocumentGenerateOperation($this->ids->get('order'));

        $document = $this->documentGenerator->generate(
            $documentType,
            [$operation->getOrderId() => $operation],
            Context::createDefaultContext()
        )->getSuccess()->first();

        static::assertInstanceOf(DocumentIdStruct::class, $document);

        $this->browser->request(
            'GET',
            '/store-api/document/download/' . $document->getId(),
            [],
            [],
            ['HTTP_ACCEPT' => $acceptHeader]
        );

        $response = $this->browser->getResponse();

        $content = $response->getContent();
        static::assertNotFalse($content);
        static::assertNotSame('', $content);
        static::assertSame(
            'inline; filename=invoice_1000.' . $expectedFileType,
            $response->headers->get('content-disposition')
        );
        static::assertStringContainsString(
            $expectedResponseContentType,
            (string) $response->headers->get('content-type')
        );
    }

    public static function provideRequestAcceptHeaderValues(): \Generator
    {
        yield 'accept header "application/pdf" returns pdf document' => [
            'documentType' => InvoiceRenderer::TYPE,
            'expectedFileType' => PdfRenderer::FILE_EXTENSION,
            'acceptHeader' => PdfRenderer::FILE_CONTENT_TYPE,
            'expectedResponseContentType' => PdfRenderer::FILE_CONTENT_TYPE,
        ];

        yield 'accept header with order "text/html;q=0.4,application/pdf;q=0.7, application/xml;q=0.1" returns pdf' => [
            'documentType' => InvoiceRenderer::TYPE,
            'expectedFileType' => PdfRenderer::FILE_EXTENSION,
            'acceptHeader' => HtmlRenderer::FILE_CONTENT_TYPE . ';q=0.4, '
                . PdfRenderer::FILE_CONTENT_TYPE . ';q=0.7, '
                . ZugferdRenderer::FILE_CONTENT_TYPE . ';q=0.1',
            'expectedResponseContentType' => PdfRenderer::FILE_CONTENT_TYPE,
        ];

        yield 'accept header with wildcard should return pdf' => [
            'documentType' => InvoiceRenderer::TYPE,
            'expectedFileType' => PdfRenderer::FILE_EXTENSION,
            'acceptHeader' => self::ACCEPT_WILDCARD,
            'expectedResponseContentType' => PdfRenderer::FILE_CONTENT_TYPE,
        ];
    }

    public function testDownloadV2DocumentWithRequestedFormat(): void
    {
        $userId = $this->loginBrowser($this->browser);
        $this->createOrder(
            $userId,
            ['channelId' => $this->ids->get('sales-channel')]
        );
        $this->seedDemoBaseConfig(DocumentType::INVOICE->value);

        $document = static::getContainer()->get(DocumentV2Generator::class)->generate(
            new DocumentGenerationRequest(
                $this->ids->get('order'),
                DocumentType::INVOICE,
                [DocumentFormat::PDF, DocumentFormat::HTML],
                documentNumber: '1000',
            ),
            Context::createDefaultContext(),
        );

        $this->browser->request(
            'GET',
            '/store-api/document/download/' . $document->getId(),
            ['format' => DocumentFormat::HTML->value],
        );

        $response = $this->browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertStringStartsWith('text/html', (string) $response->headers->get('content-type'));
        static::assertStringContainsString('<html', (string) $response->getContent());
    }

    public function testDownloadV2DocumentDefaultsToPdf(): void
    {
        $userId = $this->loginBrowser($this->browser);
        $this->createOrder(
            $userId,
            ['channelId' => $this->ids->get('sales-channel')]
        );
        $this->seedDemoBaseConfig(DocumentType::INVOICE->value);

        $document = static::getContainer()->get(DocumentV2Generator::class)->generate(
            new DocumentGenerationRequest(
                $this->ids->get('order'),
                DocumentType::INVOICE,
                [DocumentFormat::PDF, DocumentFormat::HTML],
                documentNumber: '1000',
            ),
            Context::createDefaultContext(),
        );

        $this->browser->request('GET', '/store-api/document/download/' . $document->getId());

        $response = $this->browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertStringStartsWith('application/pdf', (string) $response->headers->get('content-type'));
        static::assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function testDownloadShouldThrowExceptionWhenRequestedFileTypeHasNoGeneratedDocument(): void
    {
        $userId = $this->ids->get('user');
        $this->createOrder($userId);

        $channelContext = $this->createChannelContext([], [
            'userId' => $userId,
        ]);

        $operation = new DocumentGenerateOperation($this->ids->get('order'));

        $document = $this->documentGenerator->generate(
            InvoiceRenderer::TYPE,
            [$operation->getOrderId() => $operation],
            Context::createDefaultContext()
        )->getSuccess()->first();

        static::assertInstanceOf(DocumentIdStruct::class, $document);

        $request = new Request();
        $request->headers->set('Accept', ZugferdRenderer::FILE_CONTENT_TYPE);

        if (Feature::isActive('v6.8.0.0')) {
            $this->expectExceptionObject(
                DocumentException::documentFileTypeUnavailable($document->getId(), [ZugferdRenderer::FILE_EXTENSION])
            );
        }

        $documentRoute = static::getContainer()->get(DocumentRoute::class);

        $response = $documentRoute->download(
            $document->getId(),
            $request,
            $channelContext,
        );

        if (!Feature::isActive('v6.8.0.0')) {
            static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        }
    }

    public function testDownloadShouldThrowExceptionWithUnsupportedAcceptHeader(): void
    {
        $userId = $this->ids->get('user');
        $this->createOrder($userId);

        $channelContext = $this->createChannelContext([], [
            'userId' => $userId,
        ]);

        $operation = new DocumentGenerateOperation($this->ids->get('order'));

        $document = $this->documentGenerator->generate(
            InvoiceRenderer::TYPE,
            [$operation->getOrderId() => $operation],
            Context::createDefaultContext()
        )->getSuccess()->first();

        static::assertInstanceOf(DocumentIdStruct::class, $document);

        $request = new Request();
        $request->headers->set('Accept', self::INVALID_MIME_TYPE);

        $this->expectExceptionObject(
            DocumentException::documentAcceptHeaderMimeTypesNotSupported(
                [self::INVALID_MIME_TYPE],
                array_values(self::SUPPORTED_FILE_FORMATS)
            )
        );

        $documentRoute = static::getContainer()->get(DocumentRoute::class);

        $documentRoute->download(
            $document->getId(),
            $request,
            $channelContext,
        );
    }

    public function testDownloadShouldThrowExceptionWithDeletedUser(): void
    {
        $orderUserId = $this->ids->get('userToBeDeleted');

        $this->createUser(null, false, ['id' => $orderUserId]);

        $this->createOrder($orderUserId);

        $channelContext = $this->createChannelContext([], [
            'userId' => $orderUserId,
        ]);

        $userRepository = static::getContainer()->get('user.repository');
        $userRepository->delete([['id' => $orderUserId]], Context::createDefaultContext());

        $operation = new DocumentGenerateOperation($this->ids->get('order'));

        $document = $this->documentGenerator->generate(
            InvoiceRenderer::TYPE,
            [$operation->getOrderId() => $operation],
            Context::createDefaultContext()
        )->getSuccess()->first();

        static::assertInstanceOf(DocumentIdStruct::class, $document);

        $deepLinkCode = '';

        $request = new Request();

        $documentRoute = static::getContainer()->get(DocumentRoute::class);

        $this->expectExceptionObject(
            DocumentException::userNotLoggedIn()
        );

        $documentRoute->download(
            $document->getId(),
            $request,
            $channelContext,
            $deepLinkCode
        );
    }
}
