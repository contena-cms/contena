<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\Context;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\CartRuleLoader;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Order\OrderConverter;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Payment\Cart\PaymentHandler\PrePayment;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextRestorer;
use Contena\Core\System\Channel\Event\ChannelContextRestorerOrderCriteriaEvent;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Integration\Builder\User\UserBuilder;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
class ChannelContextRestorerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private ChannelContextRestorer $contextRestorer;

    /**
     * @var array<string, Event>
     */
    private array $events;

    private \Closure $callbackFn;

    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);

        $this->eventDispatcher = static::getContainer()->get('event_dispatcher');

        $this->events = [];

        $this->callbackFn = function (Event $event): void {
            $this->events[$event::class] = $event;
        };

        /** @var AbstractChannelContextFactory $contextFactory */
        $contextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $cartRuleLoader = static::getContainer()->get(CartRuleLoader::class);

        $this->contextRestorer = new ChannelContextRestorer(
            $contextFactory,
            $cartRuleLoader,
            static::getContainer()->get(OrderConverter::class),
            static::getContainer()->get('order.repository'),
            $this->connection,
            $this->eventDispatcher
        );
    }

    public function testRestoreByOrder(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $context = Context::createDefaultContext();
        $ids = new IdsCollection();
        $this->createOrder($ids);
        $ruleId = Uuid::randomHex();
        $rule = [
            'id' => $ruleId,
            'name' => 'Test rule',
            'priority' => 1,
            'conditions' => [
                ['type' => (new AlwaysValidRule())->getName()],
            ],
        ];

        // Create rule after create order
        static::getContainer()->get('rule.repository')
            ->create([$rule], $context);

        $saleChanelContext = $this->contextRestorer->restoreByOrder($ids->create('order'), $context);
        static::assertTrue(\in_array($ruleId, $saleChanelContext->getRuleIds(), true));
    }

    public function testRestoreByOrderUsesTheUserAddressMatchingTheOrderShippingAddress(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $ids = new IdsCollection();
        $shippingAddress = [
            'salutationId' => $this->getValidSalutationId(),
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'street' => 'Ebbinghoff 10',
            'zipcode' => '48624',
            'city' => 'Schöppingen',
            'countryId' => $this->getValidCountryId(),
        ];
        $user = $this->createUser([['id' => $ids->get('user-shipping-address'), ...$shippingAddress]]);
        $this->createOrder($ids, $user->getId(), ['id' => $ids->create('shipping-address'), ...$shippingAddress]);

        $channelContext = $this->contextRestorer->restoreByOrder($ids->get('order'), Context::createDefaultContext());

        static::assertSame($ids->get('user-shipping-address'), $channelContext->getUser()?->getActiveShippingAddress()?->getId());
    }

    public function testRestoreByUser(): void
    {
        $context = Context::createDefaultContext();
        $ids = new IdsCollection();
        $this->createOrder($ids);
        $ruleId = Uuid::randomHex();
        $rule = [
            'id' => $ruleId,
            'name' => 'Test rule',
            'priority' => 1,
            'conditions' => [
                ['type' => (new AlwaysValidRule())->getName()],
            ],
        ];

        // Create rule after create order
        static::getContainer()->get('rule.repository')
            ->create([$rule], $context);

        $saleChanelContext = $this->contextRestorer->restoreByUser($this->createUser()->getId(), $context);
        static::assertTrue(\in_array($ruleId, $saleChanelContext->getRuleIds(), true));
    }

    public function testRestoreByUserPassesStates(): void
    {
        $context = Context::createDefaultContext();
        $context->addState('foo');

        $ids = new IdsCollection();
        $this->createOrder($ids);

        $saleChanelContext = $this->contextRestorer->restoreByUser($this->createUser()->getId(), $context);
        static::assertTrue($saleChanelContext->getContext()->hasState('foo'));
    }

    public function testOrderCriteriaEventIsFired(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $context = Context::createDefaultContext();
        $ids = new IdsCollection();
        $this->createOrder($ids);

        EventHookDispatcher::fromContainer(static::getContainer())->on(ChannelContextRestorerOrderCriteriaEvent::class, $this->callbackFn);
        $this->contextRestorer->restoreByOrder($ids->create('order'), $context);

        static::assertArrayHasKey(ChannelContextRestorerOrderCriteriaEvent::class, $this->events);
        $channelContextRestorerCriteriaEvent = $this->events[ChannelContextRestorerOrderCriteriaEvent::class];
        static::assertInstanceOf(ChannelContextRestorerOrderCriteriaEvent::class, $channelContextRestorerCriteriaEvent);
    }

    /**
     * @param array<string, string>|null $shippingAddress
     */
    private function createOrder(IdsCollection $ids, ?string $userId = null, ?array $shippingAddress = null): void
    {
        $orderUser = [
            'id' => $ids->get('user'),
            'salutationId' => $this->getValidSalutationId(),
            'email' => 'test',
            'name' => 'test',
            'lastName' => 'test',
        ];

        if ($userId !== null) {
            $orderUser['userId'] = $userId;
        } else {
            $orderUser['user'] = (new UserBuilder($ids, '10000'))
                ->add('guest', true)
                ->add('createdAt', new \DateTime('- 25 hours'))->build();
        }

        $data = [
            'id' => $ids->create('order'),
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderNumber' => Uuid::randomHex(),
            'billingAddressId' => $ids->create('billing-address'),
            'currencyId' => Defaults::CURRENCY,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'channelId' => TestDefaults::CHANNEL,
            'orderDateTime' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'currencyFactor' => 1,
            'stateId' => $this->getStateId('open', 'order.state'),
            'price' => new CartPrice(200, 200, 200, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'ruleIds' => [$ids->get('rule')],
            'orderUser' => $orderUser,
            'addresses' => [
                [
                    'id' => $ids->create('billing-address'),
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'name' => 'asd',
                    'lastName' => 'asd',
                    'street' => 'asd',
                    'zipcode' => 'asd',
                    'city' => 'asd',
                ],
                $shippingAddress ?? [
                    'id' => $ids->create('shipping-address'),
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'name' => 'asd',
                    'lastName' => 'asd',
                    'street' => 'asd',
                    'zipcode' => 'asd',
                    'city' => 'asd',
                ],
            ],
            'lineItems' => [
                [
                    'id' => $ids->create('line-item'),
                    'identifier' => $ids->create('line-item'),
                    'quantity' => 1,
                    'label' => 'label',
                    'type' => LineItem::CUSTOM_LINE_ITEM_TYPE,
                    'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                ],
            ],
            'deliveries' => [
                [
                    'id' => $ids->create('delivery'),
                    'shippingOrderAddressId' => $ids->create('shipping-address'),
                    'shippingMethodId' => $this->getAvailableShippingMethod()->getId(),
                    'stateId' => $this->getStateId('open', 'order_delivery.state'),
                    'trackingCodes' => [],
                    'shippingDateEarliest' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'shippingDateLatest' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'positions' => [
                        [
                            'id' => $ids->create('position'),
                            'orderLineItemId' => $ids->create('line-item'),
                            'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        ],
                    ],
                ],
            ],
            'transactions' => [
                [
                    'id' => $ids->create('transaction'),
                    'paymentMethodId' => $this->getPrePaymentMethodId(),
                    'stateId' => $this->getStateId('open', 'order_transaction.state'),
                    'amount' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                ],
            ],
        ];

        static::getContainer()->get('order.repository')
            ->create([$data], Context::createDefaultContext());
    }

    private function getStateId(string $state, string $machine): ?string
    {
        return static::getContainer()->get(Connection::class)
            ->fetchOne(
                '
                SELECT LOWER(HEX(state_machine_state.id))
                FROM state_machine_state
                    INNER JOIN  state_machine
                    ON state_machine.id = state_machine_state.state_machine_id
                    AND state_machine.technical_name = :machine
                WHERE state_machine_state.technical_name = :state
            ',
                [
                    'state' => $state,
                    'machine' => $machine,
                ]
            );
    }

    private function getPrePaymentMethodId(): string
    {
        /** @var EntityRepository<PaymentMethodCollection> $repository */
        $repository = static::getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('handlerIdentifier', PrePayment::class));

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertIsString($id);

        return $id;
    }

    /**
     * @param list<array<string, string>> $additionalAddresses
     */
    private function createUser(array $additionalAddresses = []): UserEntity
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'addresses' => $additionalAddresses,
            'id' => $userId,
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'name' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => 'foo@bar.de',
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => '12345',
        ];

        /** @var EntityRepository<UserCollection> $repo */
        $repo = static::getContainer()->get('user.repository');

        $repo->create([$user], Context::createDefaultContext());

        /** @var UserEntity|null $user */
        $user = $repo->search(new Criteria([$userId]), Context::createDefaultContext())->getEntities()->first();

        static::assertNotNull($user);

        return $user;
    }
}
