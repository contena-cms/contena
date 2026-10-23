<?php
declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Payment\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Checkout\Payment\Cart\Token\JWTFactoryV2;
use Contena\Core\Checkout\Payment\Cart\Token\PaymentToken;
use Contena\Core\Checkout\Payment\Cart\Token\PaymentTokenGenerator;
use Contena\Core\Checkout\Payment\Cart\Token\PaymentTokenLifecycle;
use Contena\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Payment\PaymentProcessor;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Contena\Core\Test\Integration\PaymentHandler\TestPaymentHandler;
use Contena\Core\Test\Integration\Traits\OrderFixture;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class PaymentControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use OrderFixture;

    private JWTFactoryV2 $tokenFactory;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    /**
     * @var EntityRepository<OrderTransactionCollection>
     */
    private EntityRepository $orderTransactionRepository;

    /**
     * @var EntityRepository<PaymentMethodCollection>
     */
    private EntityRepository $paymentMethodRepository;

    private PaymentProcessor $paymentProcessor;

    private ?string $orderUserId = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (!Feature::isActive('v6.8.0.0')) {
            $this->tokenFactory = static::getContainer()->get(JWTFactoryV2::class);
        }

        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->orderTransactionRepository = static::getContainer()->get('order_transaction.repository');
        $this->paymentMethodRepository = static::getContainer()->get('payment_method.repository');
        $this->paymentProcessor = static::getContainer()->get(PaymentProcessor::class);
    }

    public function testCallWithoutToken(): void
    {
        $client = $this->getBrowser();

        $client->request('GET', '/payment/finalize-transaction');

        static::assertIsString($client->getResponse()->getContent());
        $response = json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('errors', $response);

        if (!Feature::isActive('v6.8.0.0')) {
            static::assertSame('FRAMEWORK__MISSING_REQUEST_PARAMETER', $response['errors'][0]['code']);
        } else {
            static::assertSame('CHECKOUT__MISSING_REQUEST_PARAMETER', $response['errors'][0]['code']);
        }
    }

    public function testCallWithInvalidToken(): void
    {
        $client = $this->getBrowser();

        $client->request('GET', '/payment/finalize-transaction?_sw_payment_token=abc');

        static::assertIsString($client->getResponse()->getContent());
        $response = json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__INVALID_PAYMENT_TOKEN', $response['errors'][0]['code']);
    }

    public function testValidTokenWithInvalidOrder(): void
    {
        $client = $this->getBrowser();

        if (Feature::isActive('v6.8.0.0')) {
            $paymentToken = new PaymentToken();
            $paymentToken->jti = Uuid::randomHex();
            $paymentToken->paymentMethodId = Uuid::randomHex();
            $paymentToken->transactionId = Uuid::randomHex();
            $paymentToken->channelId = TestDefaults::CHANNEL;
            $paymentToken->finishUrl = 'testFinishUrl';
            $token = static::getContainer()->get(PaymentTokenGenerator::class)->encode($paymentToken);
            static::getContainer()->get(PaymentTokenLifecycle::class)->addToken($paymentToken->jti, $paymentToken->exp ?? new \DateTimeImmutable());
        } else {
            $tokenStruct = new TokenStruct(null, null, Uuid::randomHex(), Uuid::randomHex(), 'testFinishUrl');
            $token = $this->tokenFactory->generateToken($tokenStruct);
        }

        $client->request('GET', '/payment/finalize-transaction?_sw_payment_token=' . $token);

        static::assertIsString($client->getResponse()->getContent());
        $response = json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__INVALID_PAYMENT_TOKEN', $response['errors'][0]['code']);
    }

    public function testValid(): void
    {
        $transaction = $this->createValidOrderTransaction();

        if (Feature::isActive('v6.8.0.0')) {
            $paymentToken = new PaymentToken();
            $paymentToken->jti = Uuid::randomHex();
            $paymentToken->paymentMethodId = $transaction->getPaymentMethodId();
            $paymentToken->transactionId = $transaction->getId();
            $paymentToken->channelId = TestDefaults::CHANNEL;
            $paymentToken->finishUrl = 'testFinishUrl';
            $token = static::getContainer()->get(PaymentTokenGenerator::class)->encode($paymentToken);
            static::getContainer()->get(PaymentTokenLifecycle::class)->addToken($paymentToken->jti, $paymentToken->exp ?? new \DateTimeImmutable());
        } else {
            $tokenStruct = new TokenStruct(null, null, $transaction->getPaymentMethodId(), $transaction->getId(), 'testFinishUrl');
            $token = $this->tokenFactory->generateToken($tokenStruct);
        }

        $client = $this->getBrowser();

        $client->request('GET', '/payment/finalize-transaction?_sw_payment_token=' . $token);

        $response = $client->getResponse();
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertStringContainsString('testFinishUrl', $response->getTargetUrl());
        static::assertTrue($response->isRedirection());
    }

    public function testCancelledPayment(): void
    {
        $transaction = $this->createValidOrderTransaction();

        if (Feature::isActive('v6.8.0.0')) {
            $paymentToken = new PaymentToken();
            $paymentToken->jti = Uuid::randomHex();
            $paymentToken->paymentMethodId = $transaction->getPaymentMethodId();
            $paymentToken->transactionId = $transaction->getId();
            $paymentToken->channelId = TestDefaults::CHANNEL;
            $paymentToken->finishUrl = 'testFinishUrl';
            $paymentToken->errorUrl = 'testErrorUrl';
            $token = static::getContainer()->get(PaymentTokenGenerator::class)->encode($paymentToken);
            static::getContainer()->get(PaymentTokenLifecycle::class)->addToken($paymentToken->jti, $paymentToken->exp ?? new \DateTimeImmutable());
        } else {
            $tokenStruct = new TokenStruct(null, null, $transaction->getPaymentMethodId(), $transaction->getId(), 'testFinishUrl', null, 'testErrorUrl');
            $token = $this->tokenFactory->generateToken($tokenStruct);
        }

        $client = $this->getBrowser();

        $client->request('GET', '/payment/finalize-transaction?_sw_payment_token=' . $token . '&cancel=1');

        $response = $client->getResponse();
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertStringContainsString('testErrorUrl', $response->getTargetUrl());
        static::assertTrue($response->isRedirection());
    }

    public function testCallTwice(): void
    {
        Feature::skipTestIfInActive('REPEATED_PAYMENT_FINALIZE', $this);

        $transaction = $this->createValidOrderTransaction();

        if (Feature::isActive('v6.8.0.0')) {
            $paymentToken = new PaymentToken();
            $paymentToken->jti = Uuid::randomHex();
            $paymentToken->paymentMethodId = $transaction->getPaymentMethodId();
            $paymentToken->transactionId = $transaction->getId();
            $paymentToken->channelId = TestDefaults::CHANNEL;
            $paymentToken->finishUrl = 'testFinishUrl';
            $token = static::getContainer()->get(PaymentTokenGenerator::class)->encode($paymentToken);
            static::getContainer()->get(PaymentTokenLifecycle::class)->addToken($paymentToken->jti, $paymentToken->exp ?? new \DateTimeImmutable());
        } else {
            $tokenStruct = new TokenStruct(null, null, $transaction->getPaymentMethodId(), $transaction->getId(), 'testFinishUrl', null, 'testErrorUrl');
            $token = $this->tokenFactory->generateToken($tokenStruct);
        }

        $client = $this->getBrowser();
        $client->request('GET', '/payment/finalize-transaction?_sw_payment_token=' . $token);
        $client->request('GET', '/payment/finalize-transaction?_sw_payment_token=' . $token);

        $response = $client->getResponse();
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertStringContainsString('testFinishUrl', $response->getTargetUrl());
        static::assertTrue($response->isRedirection());
    }

    private function getBrowser(): KernelBrowser
    {
        return KernelLifecycleManager::createBrowser(KernelLifecycleManager::getKernel(), false);
    }

    private function getChannelContext(string $paymentMethodId): ChannelContext
    {
        $options = [
            ChannelContextService::PAYMENT_METHOD_ID => $paymentMethodId,
        ];
        if ($this->orderUserId !== null) {
            $options[ChannelContextService::CUSTOMER_ID] = $this->orderUserId;
        }

        return static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL, $options);
    }

    private function createTransaction(
        string $orderId,
        string $paymentMethodId,
        Context $context
    ): string {
        $id = Uuid::randomHex();
        $transaction = [
            'id' => $id,
            'orderId' => $orderId,
            'paymentMethodId' => $paymentMethodId,
            'stateId' => static::getContainer()->get(InitialStateIdLoader::class)->get(OrderTransactionStates::STATE_MACHINE),
            'amount' => new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection(), 1),
            'payload' => '{}',
        ];

        $this->orderTransactionRepository->upsert([$transaction], $context);

        return $id;
    }

    private function createOrder(Context $context): string
    {
        $orderId = Uuid::randomHex();
        $this->orderUserId = Uuid::randomHex();

        $order = $this->getOrderData($orderId, $context, $this->orderUserId);
        $this->orderRepository->upsert($order, $context);

        return $orderId;
    }

    private function createPaymentMethod(
        Context $context,
        string $handlerIdentifier = TestPaymentHandler::class
    ): string {
        $id = Uuid::randomHex();
        $payment = [
            'id' => $id,
            'handlerIdentifier' => $handlerIdentifier,
            'name' => 'Test Payment',
            'technicalName' => 'payment_test',
            'description' => 'Test payment handler',
            'active' => true,
        ];

        $this->paymentMethodRepository->upsert([$payment], $context);

        return $id;
    }

    private function createValidOrderTransaction(): OrderTransactionEntity
    {
        $context = Context::createDefaultContext();

        $paymentMethodId = $this->createPaymentMethod($context);
        $orderId = $this->createOrder($context);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId, $context);

        $channelContext = $this->getChannelContext($paymentMethodId);

        $response = $this->paymentProcessor->pay($orderId, new Request(), $channelContext);

        static::assertNotNull($response);
        static::assertSame(TestPaymentHandler::REDIRECT_URL, $response->getTargetUrl());

        $transaction = new OrderTransactionEntity();
        $transaction->setId($transactionId);
        $transaction->setPaymentMethodId($paymentMethodId);
        $transaction->setOrderId($orderId);
        $transaction->setStateId(Uuid::randomHex());

        return $transaction;
    }
}
