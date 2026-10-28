<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\App\Checkout\Gateway;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Gateway\CheckoutGatewayException;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\Hmac\RequestSigner;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Contena\Core\Test\AppSystemTestBehaviour;
use Contena\Core\Test\Integration\PaymentHandler\TestPaymentHandler;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('checkout')]
class AppCheckoutGatewayTest extends TestCase
{
    use AppSystemTestBehaviour;
    use DatabaseTransactionBehaviour;
    use GuzzleTestClientBehaviour;
    use KernelTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createTestData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'paymentMethodId' => $this->ids->get('payment'),
            'paymentMethods' => [
                ['id' => $this->ids->get('payment')],
            ],
        ]);
    }

    public function testCheckoutGatewayReplacePaymentMethod(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../_fixtures/testGateway');

        $app = $this->fetchApp('testGateway');

        static::assertNotNull($app);
        static::assertSame('https://foo.bar/example/checkout', $app->getCheckoutGatewayUrl());

        $body = \json_encode([
            [
                'command' => 'add-payment-method',
                'payload' => [
                    'paymentMethodTechnicalName' => 'payment_new-test',
                ],
            ],
            [
                'command' => 'remove-payment-method',
                'payload' => [
                    'paymentMethodTechnicalName' => 'payment_test',
                ],
            ],
        ], flags: \JSON_THROW_ON_ERROR);

        static::assertNotNull($app->getAppSecret());

        $secret = \hash_hmac('sha256', $body, $app->getAppSecret());

        $this->appendNewResponse(new Response(200, [RequestSigner::CONTENA_APP_SIGNATURE => $secret], $body));
        $this->browser->request('POST', '/store-api/checkout/gateway');

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = $this->browser->getResponse();

        static::assertNotFalse($response->getContent());

        $response = \json_decode($response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('payments', $response, 'Response has probably errors');
        static::assertIsArray($response['payments']);
        static::assertCount(1, $response['payments']);

        $payment = $response['payments'][0];

        static::assertArrayHasKey('technicalName', $payment);
        static::assertSame('payment_new-test', $payment['technicalName']);
    }

    public function testCheckoutGatewayUnknownHandler(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../_fixtures/testGateway');

        $app = $this->fetchApp('testGateway');

        static::assertNotNull($app);
        static::assertSame('https://foo.bar/example/checkout', $app->getCheckoutGatewayUrl());

        $body = \json_encode([
            [
                'command' => 'foo',
                'payload' => [
                    'paymentMethodTechnicalName' => 'payment_new-test',
                ],
            ],
            [
                'command' => 'remove-payment-method',
                'payload' => [
                    'paymentMethodTechnicalName' => 'payment_test',
                ],
            ],
        ], flags: \JSON_THROW_ON_ERROR);

        static::assertNotNull($app->getAppSecret());

        $secret = \hash_hmac('sha256', $body, $app->getAppSecret());

        $this->appendNewResponse(new Response(200, [RequestSigner::CONTENA_APP_SIGNATURE => $secret], $body));
        $this->browser->request('POST', '/store-api/checkout/gateway');

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());

        $response = $this->browser->getResponse();

        static::assertNotFalse($response->getContent());

        $response = \json_decode($response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertIsArray($response['errors']);
        static::assertCount(1, $response['errors']);

        $error = $response['errors'][0];

        static::assertArrayHasKey('code', $error);
        static::assertSame(CheckoutGatewayException::HANDLER_NOT_FOUND_CODE, $error['code']);
    }

    public function testCheckoutGatewayMalformedAppServerResponse(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../_fixtures/testGateway');

        $app = $this->fetchApp('testGateway');

        static::assertNotNull($app);
        static::assertSame('https://foo.bar/example/checkout', $app->getCheckoutGatewayUrl());

        $body = \json_encode([
            [
                'command' => 'add-payment-method',
                'payload' => [
                    'asd' => 'payment_new-test',
                ],
            ],
            [
                'command' => 'remove-payment-method',
                'payload' => [
                    'paymentMethodTechnicalName' => 'payment_test',
                ],
            ],
        ], flags: \JSON_THROW_ON_ERROR);

        static::assertNotNull($app->getAppSecret());

        $secret = \hash_hmac('sha256', $body, $app->getAppSecret());

        $this->appendNewResponse(new Response(200, [RequestSigner::CONTENA_APP_SIGNATURE => $secret], $body));
        $this->browser->request('POST', '/store-api/checkout/gateway');

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());

        $response = $this->browser->getResponse();

        static::assertNotFalse($response->getContent());

        $response = \json_decode($response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertIsArray($response['errors']);
        static::assertCount(1, $response['errors']);

        $error = $response['errors'][0];

        static::assertArrayHasKey('code', $error);
        static::assertSame(CheckoutGatewayException::PAYLOAD_INVALID_CODE, $error['code']);
    }

    private function createTestData(): void
    {
        $payments = [
            [
                'id' => $this->ids->create('payment'),
                'name' => 'Payment 1',
                'technicalName' => 'payment_test',
                'active' => true,
                'handlerIdentifier' => TestPaymentHandler::class,
            ],
            [
                'id' => $this->ids->create('new-payment'),
                'name' => 'Payment 2',
                'technicalName' => 'payment_new-test',
                'active' => true,
                'handlerIdentifier' => TestPaymentHandler::class,
            ],
        ];

        static::getContainer()
            ->get('payment_method.repository')
            ->create($payments, Context::createDefaultContext());
    }

    private function fetchApp(string $appName): ?AppEntity
    {
        /** @var EntityRepository<AppCollection> $appRepository */
        $appRepository = static::getContainer()->get('app.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $appName));

        return $appRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
    }
}
