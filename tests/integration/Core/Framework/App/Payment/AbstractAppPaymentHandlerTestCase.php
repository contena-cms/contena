<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\App\Payment;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Contena\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Checkout\Order\OrderStates;
use Contena\Core\Checkout\Payment\Cart\PaymentRefundProcessor;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Payment\PaymentProcessor;
use Contena\Core\Defaults;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\Lifecycle\AppLifecycle;
use Contena\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\ShopId\ShopIdProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Contena\Core\System\StateMachine\StateMachineRegistry;
use Contena\Core\Test\Integration\Builder\User\UserBuilder;
use Contena\Core\Test\Integration\Builder\Order\OrderBuilder;
use Contena\Core\Test\Integration\Builder\Order\OrderTransactionBuilder;
use Contena\Core\Test\Integration\Builder\Order\OrderTransactionCaptureBuilder;
use Contena\Core\Test\Integration\Builder\Order\OrderTransactionCaptureRefundBuilder;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * @internal
 */
abstract class AbstractAppPaymentHandlerTestCase extends TestCase
{
    use GuzzleTestClientBehaviour;

    final public const ERROR_MESSAGE = 'testError';

    protected PaymentProcessor $paymentProcessor;

    protected PaymentRefundProcessor $paymentRefundProcessor;

    protected ShopIdProvider $shopIdProvider;

    protected string $shopUrl;

    protected AppEntity $app;

    protected IdsCollection $ids;

    /**
     * @var EntityRepository<OrderCollection>
     */
    protected EntityRepository $orderRepository;

    /**
     * @var EntityRepository<OrderTransactionCollection>
     */
    protected EntityRepository $orderTransactionRepository;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    /**
     * @var EntityRepository<PaymentMethodCollection>
     */
    private EntityRepository $paymentMethodRepository;

    private StateMachineRegistry $stateMachineRegistry;

    private InitialStateIdLoader $initialStateIdLoader;

    private AbstractChannelContextFactory $channelContextFactory;

    /**
     * @var EntityRepository<OrderTransactionCaptureCollection>
     */
    private EntityRepository $orderTransactionCaptureRepository;

    /**
     * @var EntityRepository<OrderTransactionCaptureRefundCollection>
     */
    private EntityRepository $orderTransactionCaptureRefundRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->userRepository = static::getContainer()->get('user.repository');
        $this->paymentMethodRepository = static::getContainer()->get('payment_method.repository');
        $this->orderTransactionRepository = static::getContainer()->get('order_transaction.repository');
        $this->orderTransactionCaptureRepository = static::getContainer()->get('order_transaction_capture.repository');
        $this->orderTransactionCaptureRefundRepository = static::getContainer()->get('order_transaction_capture_refund.repository');
        $this->stateMachineRegistry = static::getContainer()->get(StateMachineRegistry::class);
        $this->initialStateIdLoader = static::getContainer()->get(InitialStateIdLoader::class);
        $this->channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $this->shopUrl = $_SERVER['APP_URL'];
        $this->shopIdProvider = static::getContainer()->get(ShopIdProvider::class);
        $this->paymentProcessor = static::getContainer()->get(PaymentProcessor::class);
        $this->paymentRefundProcessor = static::getContainer()->get(PaymentRefundProcessor::class);
        $this->context = Context::createDefaultContext();

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/testPayments/manifest.xml');

        $appLifecycle = static::getContainer()->get(AppLifecycle::class);
        $appLifecycle->install($manifest, new AppInstallParameters(), $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'testPayments'));
        /** @var EntityRepository<AppCollection> $appRepository */
        $appRepository = static::getContainer()->get('app.repository');

        $app = $appRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertNotNull($app);
        $this->app = $app;
        $this->ids = new IdsCollection();

        $this->resetHistory();
    }

    protected function createUser(): string
    {
        $userId = $this->ids->get('user');
        $addressId = $this->ids->get('address');

        $user = (new UserBuilder($this->ids, '1337'))
            ->firstName('Max')
            ->lastName('Mustermann')
            ->add('id', $this->ids->get('user'))
            ->add('email', Uuid::randomHex() . '@example.com')
            ->add('channelId', TestDefaults::CHANNEL)
            ->add('password', 'contena')
            ->defaultShippingAddress('address')
            ->defaultBillingAddress('address', [
                'id' => $addressId,
                'userId' => $userId,
                'countryId' => $this->getValidCountryId(),
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Ebbinghoff 10',
                'zipcode' => '48624',
                'city' => 'Schöppingen',
            ])
            ->userGroup(TestDefaults::FALLBACK_CUSTOMER_GROUP);

        $this->userRepository->upsert([$user->build()], $this->context);

        return $userId;
    }

    protected function createOrder(string $paymentMethodId): string
    {
        $orderId = $this->ids->get('order');
        $addressId = $this->ids->get('address');

        $this->ids->set(
            'state',
            static::getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE)
        );

        $stateId = $this->ids->get('state');
        $userId = $this->createUser();

        $order = (new OrderBuilder($this->ids, '10000'))
            ->add('id', $this->ids->get('order'))
            ->add('orderDateTime', (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT))
            ->add('price', new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET))
            ->add('shippingCosts', new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()))
            ->add('orderUser', [
                'userId' => $userId,
                'email' => 'test@example.com',
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
            ])
            ->add('stateId', $stateId)
            ->add('paymentMethodId', $paymentMethodId)
            ->add('currencyId', Defaults::CURRENCY)
            ->add('currencyFactor', 1.0)
            ->add('channelId', TestDefaults::CHANNEL)
            ->addAddress('address', [
                'id' => $addressId,
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Ebbinghoff 10',
                'zipcode' => '48624',
                'city' => 'Schöppingen',
                'countryId' => $this->getValidCountryId(),
            ])
            ->add('billingAddressId', $addressId)
            ->add('shippingAddressId', $addressId)
            ->add('context', '{}')
            ->add('payload', '{}')
            ->build();

        $this->orderRepository->upsert([$order], $this->context);

        return $orderId;
    }

    protected function createTransaction(string $orderId, string $paymentMethodId): string
    {
        $this->ids->set(
            'transaction_state',
            $this->initialStateIdLoader->get(OrderTransactionStates::STATE_MACHINE)
        );

        $transaction = (new OrderTransactionBuilder($this->ids, 'transaction'))
            ->add('orderId', $orderId)
            ->add('paymentMethodId', $paymentMethodId)
            ->add('stateId', $this->ids->get('transaction_state'))
            ->add('payload', '{}')
            ->amount(100)
            ->build();

        $this->orderTransactionRepository->upsert([$transaction], $this->context);

        return $this->ids->get('transaction');
    }

    protected function createCapture(string $orderTransactionId): string
    {
        $capture = (new OrderTransactionCaptureBuilder($this->ids, 'capture', $orderTransactionId))
            ->build();

        $this->orderTransactionCaptureRepository->upsert([$capture], $this->context);

        return $this->ids->get('capture');
    }

    protected function createRefund(string $captureId): string
    {
        $refund = (new OrderTransactionCaptureRefundBuilder($this->ids, 'refund', $captureId))
            ->build();

        $this->orderTransactionCaptureRefundRepository->upsert([$refund], $this->context);

        return $this->ids->get('refund');
    }

    protected function getPaymentMethodId(string $name): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', \sprintf('app\\testPayments_%s', $name)));
        $id = $this->paymentMethodRepository->searchIds($criteria, $this->context)->firstId();
        static::assertNotNull($id);

        return $id;
    }

    protected function getChannelContext(string $paymentMethodId): ChannelContext
    {
        return $this->channelContextFactory->create(
            Uuid::randomHex(),
            TestDefaults::CHANNEL,
            [
                ChannelContextService::PAYMENT_METHOD_ID => $paymentMethodId,
                ChannelContextService::CUSTOMER_ID => $this->ids->get('user'),
            ]
        );
    }

    /**
     * @param array<int|string, mixed> $content
     */
    protected function signResponse(array $content): ResponseInterface
    {
        $json = \json_encode($content, \JSON_THROW_ON_ERROR);
        static::assertIsString($json);

        $secret = $this->app->getAppSecret();
        static::assertNotNull($secret);

        $hmac = \hash_hmac('sha256', $json, $secret);

        return new Response(
            200,
            [
                'contena-app-signature' => $hmac,
            ],
            $json
        );
    }

    protected function assertOrderTransactionState(string $state, string $transactionId): void
    {
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('state');

        $transaction = $this->orderTransactionRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertNotNull($transaction);

        $states = $this->stateMachineRegistry->getStateMachine(OrderTransactionStates::STATE_MACHINE, $this->context)->getStates();
        static::assertNotNull($states);
        $actualState = $states->get($transaction->getStateId());
        static::assertNotNull($actualState);
        static::assertSame($state, $actualState->getTechnicalName());
    }

    protected function assertRefundState(string $state, string $refundId): void
    {
        $criteria = new Criteria([$refundId]);
        $criteria->addAssociation('state');

        $refund = $this->orderTransactionCaptureRefundRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertNotNull($refund);

        $states = $this->stateMachineRegistry->getStateMachine(OrderTransactionCaptureRefundStates::STATE_MACHINE, $this->context)->getStates();
        static::assertNotNull($states);
        $actualState = $states->get($refund->getStateId());
        static::assertNotNull($actualState);
        static::assertSame($state, $actualState->getTechnicalName());
    }
}
