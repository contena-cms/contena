<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Payment\OpenApi;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\OpenApi\Api\OpenApiResponse;
use Contena\Core\System\Payment\OpenApi\Api\PaymentController;
use Contena\Core\System\Payment\OpenApi\OpenApiException;
use Contena\Core\System\Payment\OpenApi\Request\PaymentRequestMapper;
use Contena\Core\System\Payment\OpenApi\Util\SignUtil;
use Contena\Core\System\Payment\Payment\Struct\OrderReference;
use Contena\Core\System\Payment\Payment\Struct\PaymentRequest;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Refund\Struct\RefundRequest;
use Contena\Core\System\Payment\Service\AbstractPaymentService;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\Payment\Subscription\Struct\SubscriptionRequest;
use Contena\Core\System\Payment\Transfer\Struct\TransferRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
final class OpenApiTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const string APP_SECRET = 'payment-api-secret';

    private static ?OpenApiServiceStub $sharedPaymentService = null;

    private KernelBrowser $browser;

    private OpenApiServiceStub $paymentService;

    private string $appId;

    private string $appCode;

    private string $tenantId;

    protected function setUp(): void
    {
        $this->tenantId = $this->createTenant('Payment API tenant')->id;
        $this->appId = Uuid::randomHex();
        $this->appCode = 'payment-api-' . bin2hex(random_bytes(6));
        $this->appRepository()->create([[
            'id' => $this->appId,
            'appCode' => $this->appCode,
            'appSecret' => self::APP_SECRET,
            'name' => 'Payment API app',
            'status' => true,
        ]], Context::createTenantContext($this->tenantId));

        if (!self::$sharedPaymentService instanceof OpenApiServiceStub) {
            self::$sharedPaymentService = new OpenApiServiceStub();
            static::getContainer()->set(PaymentController::class, new PaymentController(
                self::$sharedPaymentService,
                static::getContainer()->get('request_stack'),
                new PaymentRequestMapper(),
            ));
        }
        self::$sharedPaymentService->reset();
        $this->paymentService = self::$sharedPaymentService;
        $this->browser = KernelLifecycleManager::createBrowser(static::getKernel());
    }

    public function testSignedPaymentRequestUsesTheAuthenticatedTenantAndReturnsAUnifiedResult(): void
    {
        $parameters = $this->signed([
            'external_order_no' => 'app-order-1',
            'amount' => 1250,
            'currency_code' => 'cny',
            'method_code' => 'h5',
            'subject' => 'Test order',
            'channel_code' => 'alipay',
            'notify_url' => 'https://app.example/notify',
            'return_url' => 'https://app.example/return',
            'channel_extra' => ['buyer_id' => 'buyer-1'],
        ]);

        $this->browser->jsonRequest('POST', '/api/payment/pay', $parameters);

        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $body = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame(OpenApiResponse::SUCCESS, $body['code']);
        static::assertSame([
            'resource_no' => 'platform-resource',
            'external_resource_no' => 'app-resource',
            'transaction_no' => 'platform-transaction',
            'status' => PaymentStatus::PENDING,
            'action' => PaymentResult::ACTION_REDIRECT,
            'action_value' => 'https://cashier.example/pay',
        ], $body['data']);

        static::assertSame('pay', $this->paymentService->operation);
        static::assertSame($this->tenantId, $this->paymentService->context?->getTenantId());
        static::assertSame($this->appCode, $this->paymentService->app?->appCode);
        $paymentRequest = $this->paymentService->request;
        static::assertInstanceOf(PaymentRequest::class, $paymentRequest);
        static::assertSame('app-order-1', $paymentRequest->externalOrderNo);
        static::assertSame(1250, $paymentRequest->amount);
        static::assertSame(['buyer_id' => 'buyer-1'], $paymentRequest->extra);
    }

    public function testClientIpIsCapturedFromTheHttpRequestInsteadOfThePayload(): void
    {
        $this->browser->jsonRequest(
            'POST',
            '/api/payment/pay',
            $this->signed([
                'external_order_no' => 'app-order-ip',
                'amount' => 1250,
                'method_code' => 'h5',
                'subject' => 'Test order',
            ]),
            ['REMOTE_ADDR' => '198.51.100.7'],
        );

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());
        static::assertInstanceOf(PaymentRequest::class, $this->paymentService->request);
        static::assertSame('198.51.100.7', $this->paymentService->request->clientIp);
    }

    public function testInvalidSignatureUsesTheOpenApiErrorEnvelope(): void
    {
        $parameters = $this->signed(['order_no' => 'platform-order']);
        $parameters['sign'] = str_repeat('0', 64);

        $this->browser->jsonRequest('POST', '/api/payment/query', $parameters);

        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame(OpenApiException::INVALID_SIGNATURE, $body['code']);
        static::assertNull($body['data']);
        static::assertNull($this->paymentService->operation);
    }

    public function testExpiredSignatureIsRejectedBeforeThePaymentServiceIsCalled(): void
    {
        $parameters = $this->signed(['order_no' => 'platform-order']);
        $parameters['timestamp'] = (string) (time() - SignUtil::TIMESTAMP_TOLERANCE - 1);
        $parameters['sign'] = SignUtil::sign($parameters, self::APP_SECRET);

        $this->browser->jsonRequest('POST', '/api/payment/query', $parameters);

        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame(OpenApiException::INVALID_SIGNATURE, $body['code']);
        static::assertNull($this->paymentService->operation);
    }

    public function testMissingRequiredParameterUsesTheOpenApiErrorEnvelope(): void
    {
        $this->browser->jsonRequest('POST', '/api/payment/pay', $this->signed([
            'amount' => 1250,
            'method_code' => 'h5',
            'subject' => 'Test order',
        ]));

        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame(OpenApiException::MISSING_PARAMETER, $body['code']);
        static::assertNull($body['data']);
        static::assertNull($this->paymentService->operation);
    }

    public function testAppSecretIsAvailableForSigningButHiddenFromGenericApis(): void
    {
        $app = $this->appRepository()->search(new Criteria([$this->appId]), Context::createGlobalContext())->getEntities()->first();
        static::assertInstanceOf(PaymentAppEntity::class, $app);
        static::assertSame(self::APP_SECRET, $app->appSecret);

        $field = static::getContainer()->get(PaymentAppDefinition::class)->getFields()->get('appSecret');
        static::assertNotNull($field);
        static::assertNull($field->getFlag(ApiAware::class));
    }

    public function testProviderNotificationRouteDoesNotRequireAppAuthentication(): void
    {
        $this->browser->request(
            'POST',
            '/api/payment/notify/alipay/' . Uuid::randomHex(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYMENT_TEST' => 'header-value'],
            content: '{"event":"payment.succeeded"}',
        );

        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame(PaymentException::CHANNEL_CONFIG_NOT_FOUND, $body['code']);
        static::assertNull($body['data']);
    }

    /**
     * @param array<string, mixed> $parameters
     * @param class-string $requestClass
     */
    #[DataProvider('operationProvider')]
    public function testTranslatesTheUnifiedOperationContracts(string $path, string $operation, array $parameters, string $requestClass): void
    {
        $this->browser->jsonRequest('POST', $path, $this->signed($parameters));

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());
        static::assertSame($operation, $this->paymentService->operation);
        static::assertInstanceOf($requestClass, $this->paymentService->request);
        static::assertSame($this->tenantId, $this->paymentService->context?->getTenantId());
    }

    /**
     * @return iterable<string, array{string, string, array<string, mixed>, class-string}>
     */
    public static function operationProvider(): iterable
    {
        yield 'query by app order number' => [
            '/api/payment/query',
            'query',
            ['external_order_no' => 'app-order-1'],
            OrderReference::class,
        ];
        yield 'refund a platform order' => [
            '/api/payment/refund',
            'refund',
            ['order_no' => 'platform-order', 'external_refund_no' => 'app-refund-1', 'refund_amount' => 500],
            RefundRequest::class,
        ];
        yield 'create a recurring agreement' => [
            '/api/payment/subscribe',
            'subscribe',
            ['external_subscription_no' => 'app-subscription-1', 'channel_code' => 'paypal', 'period' => 1],
            SubscriptionRequest::class,
        ];
        yield 'transfer funds to a payee' => [
            '/api/payment/transfer',
            'transfer',
            ['external_transfer_no' => 'app-transfer-1', 'amount' => 500, 'payee' => 'payee', 'payee_name' => 'Payee'],
            TransferRequest::class,
        ];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    #[DataProvider('invalidRequests')]
    public function testInvalidInputIsRejectedBeforeCallingTheBusinessService(string $operation, array $parameters, string $errorCode): void
    {
        $this->browser->jsonRequest('POST', '/api/payment/' . $operation, $this->signed($parameters));

        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        $body = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame($errorCode, $body['code']);
        static::assertNull($body['data']);
        static::assertNull($this->paymentService->operation);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function invalidRequests(): iterable
    {
        $pay = ['external_order_no' => 'order', 'amount' => 100, 'method_code' => 'h5', 'subject' => 'Subject'];
        $refund = ['order_no' => 'order', 'external_refund_no' => 'refund', 'refund_amount' => 100];
        $transfer = ['external_transfer_no' => 'transfer', 'amount' => 100, 'payee' => 'account', 'payee_name' => 'Name'];
        $subscribe = ['external_subscription_no' => 'agreement'];

        yield 'payment requires business reference' => ['pay', array_replace($pay, ['external_order_no' => '  ']), OpenApiException::MISSING_PARAMETER];
        yield 'payment rejects negative money' => ['pay', array_replace($pay, ['amount' => -1]), PaymentException::INVALID_REQUEST];
        yield 'payment rejects zero money' => ['pay', array_replace($pay, ['amount' => 0]), PaymentException::INVALID_REQUEST];
        yield 'payment requires method' => ['pay', array_replace($pay, ['method_code' => '']), OpenApiException::MISSING_PARAMETER];
        yield 'payment requires subject' => ['pay', array_replace($pay, ['subject' => '  ']), OpenApiException::MISSING_PARAMETER];
        yield 'payment currency cannot be numeric' => ['pay', array_replace($pay, ['currency_code' => '123']), PaymentException::INVALID_REQUEST];
        yield 'payment currency cannot be blank' => ['pay', array_replace($pay, ['currency_code' => '']), OpenApiException::MISSING_PARAMETER];
        yield 'refund requires business reference' => ['refund', array_replace($refund, ['external_refund_no' => '']), OpenApiException::MISSING_PARAMETER];
        yield 'refund rejects negative money' => ['refund', array_replace($refund, ['refund_amount' => -1]), PaymentException::INVALID_REQUEST];
        yield 'transfer requires business reference' => ['transfer', array_replace($transfer, ['external_transfer_no' => ' ']), OpenApiException::MISSING_PARAMETER];
        yield 'transfer rejects zero money' => ['transfer', array_replace($transfer, ['amount' => 0]), PaymentException::INVALID_REQUEST];
        yield 'transfer requires payee' => ['transfer', array_replace($transfer, ['payee' => ' ']), OpenApiException::MISSING_PARAMETER];
        yield 'transfer requires payee name' => ['transfer', array_replace($transfer, ['payee_name' => ' ']), OpenApiException::MISSING_PARAMETER];
        yield 'transfer currency cannot be numeric' => ['transfer', array_replace($transfer, ['currency_code' => '123']), PaymentException::INVALID_REQUEST];
        yield 'agreement requires reference' => ['subscribe', array_replace($subscribe, ['external_subscription_no' => ' ']), OpenApiException::MISSING_PARAMETER];
        yield 'agreement period cannot be negative' => ['subscribe', array_replace($subscribe, ['period' => -1]), PaymentException::INVALID_REQUEST];
        yield 'agreement single amount cannot be zero' => ['subscribe', array_replace($subscribe, ['single_amount' => 0]), PaymentException::INVALID_REQUEST];
        yield 'agreement total amount cannot be negative' => ['subscribe', array_replace($subscribe, ['total_amount' => -1]), PaymentException::INVALID_REQUEST];
        yield 'agreement payment count cannot be negative' => ['subscribe', array_replace($subscribe, ['total_payments' => -1]), PaymentException::INVALID_REQUEST];
        yield 'query requires an order reference' => ['query', [], PaymentException::INVALID_REQUEST];
        yield 'query rejects blank references' => ['query', ['order_no' => ' ', 'external_order_no' => ' '], PaymentException::INVALID_REQUEST];
        yield 'refund requires an order reference' => ['refund', ['external_refund_no' => 'refund', 'refund_amount' => 100], PaymentException::INVALID_REQUEST];
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    private function signed(array $parameters): array
    {
        $parameters = [
            'app_id' => $this->appCode,
            'timestamp' => (string) time(),
            'nonce' => bin2hex(random_bytes(8)),
            ...$parameters,
        ];
        $parameters['sign'] = SignUtil::sign($parameters, self::APP_SECRET);

        return $parameters;
    }

    /**
     * @return EntityRepository<PaymentAppCollection>
     */
    private function appRepository(): EntityRepository
    {
        return static::getContainer()->get('payment_app.repository');
    }
}

/**
 * @internal
 */
final class OpenApiServiceStub extends AbstractPaymentService
{
    public ?string $operation = null;

    public ?PaymentAppEntity $app = null;

    public PaymentRequest|OrderReference|RefundRequest|SubscriptionRequest|TransferRequest|null $request = null;

    public ?Context $context = null;

    public function reset(): void
    {
        $this->operation = null;
        $this->app = null;
        $this->request = null;
        $this->context = null;
    }

    public function getDecorated(): AbstractPaymentService
    {
        return $this;
    }

    public function pay(PaymentAppEntity $app, PaymentRequest $request, Context $context): PaymentResult
    {
        return $this->record('pay', $app, $request, $context);
    }

    public function query(PaymentAppEntity $app, OrderReference $request, Context $context): PaymentResult
    {
        return $this->record('query', $app, $request, $context);
    }

    public function refund(PaymentAppEntity $app, RefundRequest $request, Context $context): PaymentResult
    {
        return $this->record('refund', $app, $request, $context);
    }

    public function transfer(PaymentAppEntity $app, TransferRequest $request, Context $context): PaymentResult
    {
        return $this->record('transfer', $app, $request, $context);
    }

    public function subscribe(PaymentAppEntity $app, SubscriptionRequest $request, Context $context): PaymentResult
    {
        return $this->record('subscribe', $app, $request, $context);
    }

    private function record(
        string $operation,
        PaymentAppEntity $app,
        PaymentRequest|OrderReference|RefundRequest|SubscriptionRequest|TransferRequest $request,
        Context $context,
    ): PaymentResult {
        $this->operation = $operation;
        $this->app = $app;
        $this->request = $request;
        $this->context = $context;

        return new PaymentResult(
            PaymentStatus::PENDING,
            PaymentResult::ACTION_REDIRECT,
            'https://cashier.example/pay',
            providerRequestId: 'hidden-provider-request',
            providerResourceId: 'hidden-provider-resource',
            data: ['hidden' => true],
            resourceNo: 'platform-resource',
            externalResourceNo: 'app-resource',
            transactionNo: 'platform-transaction',
        );
    }
}
