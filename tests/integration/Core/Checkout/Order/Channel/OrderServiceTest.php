<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Order\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Contena\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Checkout\Order\Channel\OrderService;
use Contena\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Contena\Core\Content\Flow\Dispatching\BufferedFlowExecutor;
use Contena\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Contena\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\CountryAddToChannelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainDefinition;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Controller\AccountOrderController;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * @internal
 */
class OrderServiceTest extends TestCase
{
    use CountryAddToChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use MailTemplateTestBehaviour;

    private ChannelContext $channelContext;

    private OrderService $orderService;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderService = static::getContainer()->get(OrderService::class);

        $this->orderRepository = static::getContainer()->get('order.repository');

        $this->cleanDefaultChannelDomain();
        $this->addCountriesToChannel();

        $contextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $this->channelContext = $contextFactory->create(
            '',
            TestDefaults::CHANNEL,
            [ChannelContextService::CUSTOMER_ID => $this->createUser('Jon', 'Doe')]
        );
    }

    public function testOrderDeliveryStateTransition(): void
    {
        $orderId = $this->performOrder();

        // getting the id of the order delivery
        $criteria = new Criteria([$orderId]);

        $criteria->addAssociations([
            'stateMachineState',
            'deliveries.stateMachineState',
            'deliveries.shippingOrderAddress',
        ]);

        $order = $this->orderRepository->search($criteria, $this->channelContext->getContext())->getEntities()->first();
        static::assertNotNull($order);
        $deliveries = $order->getDeliveries();
        static::assertNotNull($deliveries);
        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        $orderDeliveryId = $delivery->getId();

        $this->orderService->orderDeliveryStateTransition(
            $orderDeliveryId,
            'ship',
            new RequestDataBag(),
            $this->channelContext->getContext()
        );

        $updatedOrder = $this->orderRepository->search($criteria, $this->channelContext->getContext())->getEntities()->first();
        static::assertNotNull($updatedOrder);
        $deliveries = $updatedOrder->getDeliveries();
        static::assertNotNull($deliveries);
        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertNotNull($delivery->getStateMachineState());
        $updatedDeliveryState = $delivery->getStateMachineState()->getTechnicalName();

        static::assertSame('shipped', $updatedDeliveryState);
    }

    public function testOrderDeliveryStateTransitionSendsMail(): void
    {
        if (!static::getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without frontend in NEXT-16882');
        }

        $orderId = $this->performOrder();

        // getting the id of the order delivery
        $criteria = new Criteria([$orderId]);

        $criteria->addAssociations([
            'stateMachineState',
            'deliveries.stateMachineState',
            'deliveries.shippingOrderAddress',
        ]);

        $order = $this->orderRepository->search($criteria, $this->channelContext->getContext())->getEntities()->first();
        static::assertNotNull($order);
        $deliveries = $order->getDeliveries();
        static::assertNotNull($deliveries);
        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        $orderDeliveryId = $delivery->getId();

        $domain = 'http://contena.' . Uuid::randomHex();
        $this->setDomainForChannel($domain, Defaults::LANGUAGE_SYSTEM);

        $url = $domain . '/account/order/' . $order->getDeepLinkCode();
        $mail = null;
        $this->catchEvent(MailSentEvent::class, $mail);

        $this->orderService->orderDeliveryStateTransition(
            $orderDeliveryId,
            'cancel',
            new RequestDataBag(),
            $this->channelContext->getContext()
        );
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        static::assertInstanceOf(MailSentEvent::class, $mail, 'The mail.sent Event did not run');
        $htmlText = $mail->getContents()['text/html'];
        static::assertIsString($htmlText);
        static::assertStringContainsString('The new status is as follows: Cancelled.', $htmlText);
        static::assertStringContainsString($url, $htmlText);
    }

    public function testSkipOrderDeliveryStateTransitionSendsMail(): void
    {
        if (!static::getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without frontend in NEXT-16882');
        }

        $orderId = $this->performOrder();

        // getting the id of the order delivery
        $criteria = new Criteria([$orderId]);

        $criteria->addAssociations([
            'stateMachineState',
            'deliveries.stateMachineState',
            'deliveries.shippingOrderAddress',
        ]);

        $order = $this->orderRepository->search($criteria, $this->channelContext->getContext())->getEntities()->first();
        static::assertNotNull($order);
        static::assertNotNull($deliveries = $order->getDeliveries());
        static::assertNotNull($delivery = $deliveries->first());
        $orderDeliveryId = $delivery->getId();

        $domain = 'http://contena.' . Uuid::randomHex();
        $this->setDomainForChannel($domain, Defaults::LANGUAGE_SYSTEM);

        $mail = null;
        $this->catchEvent(MailSentEvent::class, $mail);

        $this->channelContext
            ->getContext()
            ->addExtension(SendMailAction::MAIL_CONFIG_EXTENSION, new MailSendSubscriberConfig(true, [], []));

        $this->orderService->orderDeliveryStateTransition(
            $orderDeliveryId,
            'cancel',
            new RequestDataBag(['sendMail' => false]),
            $this->channelContext->getContext()
        );

        static::assertNull($mail, 'The mail.sent Event did run');
    }

    public function testOrderDeliveryStateTransitionSendsMailDe(): void
    {
        if (!static::getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without frontend in NEXT-16882');
        }

        $contextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $previousContext = $this->channelContext;
        $this->channelContext = $contextFactory->create(
            '',
            TestDefaults::CHANNEL,
            [
                ChannelContextService::CUSTOMER_ID => $this->createUser('Jon', 'De'),
                ChannelContextService::LANGUAGE_ID => $this->getDeDeLanguageId(),
            ]
        );
        $orderId = $this->performOrder();

        // getting the id of the order delivery
        $criteria = new Criteria([$orderId]);

        $criteria->addAssociations([
            'stateMachineState',
            'deliveries.stateMachineState',
            'deliveries.shippingOrderAddress',
        ]);

        $order = $this->orderRepository->search($criteria, $this->channelContext->getContext())->getEntities()->first();
        static::assertNotNull($order);
        static::assertNotNull($deliveries = $order->getDeliveries());
        static::assertNotNull($delivery = $deliveries->first());
        $orderDeliveryId = $delivery->getId();

        $domain = 'http://contena.' . Uuid::randomHex();
        $this->setDomainForChannel($domain, $this->getDeDeLanguageId());

        $url = $domain . '/account/order/' . $order->getDeepLinkCode();
        $mail = null;
        $this->catchEvent(MailSentEvent::class, $mail);

        $this->orderService->orderDeliveryStateTransition(
            $orderDeliveryId,
            'cancel',
            new RequestDataBag(),
            Context::createDefaultContext() // DefaultContext is intended to test if the language of the order is used
        );
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        static::assertInstanceOf(MailSentEvent::class, $mail, 'The mail.sent Event did not run');
        $textHtml = $mail->getContents()['text/html'];
        static::assertIsString($textHtml);
        static::assertStringContainsString('Die Bestellung hat jetzt den Lieferstatus: Abgebrochen.', $textHtml);
        static::assertStringContainsString($url, $textHtml);
        $this->channelContext = $previousContext;
    }

    public function testOrderTransactionStateTransition(): void
    {
        $orderId = $this->performOrder();

        // getting the id of the order transaction
        $criteria = new Criteria([$orderId]);

        $criteria->addAssociation('transactions.stateMachineState');

        $order = $this->orderRepository->search($criteria, $this->channelContext->getContext())->getEntities()->first();
        static::assertNotNull($order);
        static::assertNotNull($transactions = $order->getTransactions());
        static::assertNotNull($transaction = $transactions->first());
        $orderTransactionId = $transaction->getId();

        $this->orderService->orderTransactionStateTransition(
            $orderTransactionId,
            'remind',
            new RequestDataBag(),
            $this->channelContext->getContext()
        );

        $updatedOrder = $this->orderRepository->search($criteria, $this->channelContext->getContext())->getEntities()->first();
        static::assertNotNull($updatedOrder);
        static::assertNotNull($transactions = $updatedOrder->getTransactions());
        static::assertNotNull($transaction = $transactions->first());
        static::assertNotNull($transaction->getStateMachineState());
        $updatedTransactionState = $transaction->getStateMachineState()->getTechnicalName();

        static::assertSame('reminded', $updatedTransactionState);
    }

    public function testOrderTransactionStateTransitionSendsMail(): void
    {
        if (!static::getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without frontend in NEXT-16882');
        }

        $orderId = $this->performOrder();

        // getting the id of the order transaction
        $criteria = new Criteria([$orderId]);

        $criteria->addAssociations([
            'stateMachineState',
            'transactions.stateMachineState',
        ]);

        $order = $this->orderRepository->search($criteria, $this->channelContext->getContext())->getEntities()->first();
        static::assertNotNull($order);
        static::assertNotNull($transactions = $order->getTransactions());
        static::assertNotNull($transaction = $transactions->first());
        $orderTransactionId = $transaction->getId();

        $domain = 'http://contena.' . Uuid::randomHex();
        $this->setDomainForChannel($domain, Defaults::LANGUAGE_SYSTEM);

        $url = $domain . '/account/order/' . $order->getDeepLinkCode();
        $mail = null;
        $this->catchEvent(MailSentEvent::class, $mail);

        $this->orderService->orderTransactionStateTransition(
            $orderTransactionId,
            'paid_partially',
            new RequestDataBag(),
            $this->channelContext->getContext()
        );
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        static::assertInstanceOf(MailSentEvent::class, $mail, 'The mail.sent Event did not run');
        $htmlText = $mail->getContents()['text/html'];
        static::assertIsString($htmlText);
        static::assertStringContainsString('The new status is as follows: Paid (partially).', $htmlText);
        static::assertStringContainsString($url, $htmlText);
    }

    public function testSkipOrderTransactionStateTransitionSendsMail(): void
    {
        if (!static::getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without frontend in NEXT-16882');
        }

        $orderId = $this->performOrder();

        // getting the id of the order transaction
        $criteria = new Criteria([$orderId]);

        $criteria->addAssociations([
            'stateMachineState',
            'transactions.stateMachineState',
        ]);

        $order = $this->orderRepository->search($criteria, $this->channelContext->getContext())->getEntities()->first();
        static::assertNotNull($order);
        static::assertNotNull($transactions = $order->getTransactions());
        static::assertNotNull($transaction = $transactions->first());
        $orderTransactionId = $transaction->getId();

        $domain = 'http://contena.' . Uuid::randomHex();
        $this->setDomainForChannel($domain, Defaults::LANGUAGE_SYSTEM);

        $mail = null;
        $this->catchEvent(MailSentEvent::class, $mail);

        $this->channelContext
            ->getContext()
            ->addExtension(SendMailAction::MAIL_CONFIG_EXTENSION, new MailSendSubscriberConfig(true, [], []));

        $this->orderService->orderTransactionStateTransition(
            $orderTransactionId,
            'paid_partially',
            new RequestDataBag(['sendMail' => false]),
            $this->channelContext->getContext()
        );

        static::assertNull($mail, 'The mail.sent Event did run');
    }

    public function testCreateOrder(): void
    {
        $data = new RequestDataBag(['tos' => true]);
        $this->fillCart($this->channelContext->getToken());

        $orderId = $this->orderService->createOrder($data, $this->channelContext);

        $criteria = new Criteria([$orderId]);

        $criteria->addAssociation('stateMachineState');

        $newlyCreatedOrder = $this->orderRepository->search($criteria, $this->channelContext->getContext())->getEntities()->first();

        static::assertInstanceOf(OrderEntity::class, $newlyCreatedOrder);
        static::assertSame($orderId, $newlyCreatedOrder->getId());
    }

    public function testCreateOrderSavesVatIdsInOrderUser(): void
    {
        $vatIds = ['DE123456789'];
        $additionalData = [
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'billingAddress' => [
                'company' => 'Test Company',
                'department' => 'Test Department',
            ],
            'vatIds' => $vatIds,
        ];
        $contextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $this->channelContext = $contextFactory->create(
            '',
            TestDefaults::CHANNEL,
            [ChannelContextService::CUSTOMER_ID => $this->createUser('Jon', 'Doe', $additionalData)]
        );

        $data = new RequestDataBag(['tos' => true]);
        $this->fillCart($this->channelContext->getToken());

        $orderId = $this->orderService->createOrder($data, $this->channelContext);

        $criteria = new Criteria([$orderId]);

        $newlyCreatedOrder = $this->orderRepository->search($criteria, $this->channelContext->getContext())->getEntities()->first();

        static::assertInstanceOf(OrderEntity::class, $newlyCreatedOrder);
        static::assertSame($orderId, $newlyCreatedOrder->getId());
        $orderUser = $newlyCreatedOrder->getOrderUser();
        static::assertNotNull($orderUser);
        static::assertSame($vatIds, $orderUser->getVatIds());
    }

    public function testCreateOrderSendsMail(): void
    {
        if (!static::getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without frontend in NEXT-16882');
        }

        $data = new RequestDataBag(['tos' => true]);
        $this->fillCart($this->channelContext->getToken());

        $domain = 'http://contena.' . Uuid::randomHex();
        $this->setDomainForChannel($domain, Defaults::LANGUAGE_SYSTEM);

        $mail = null;
        $this->catchEvent(MailSentEvent::class, $mail);

        $this->orderService->createOrder($data, $this->channelContext);
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        static::assertInstanceOf(MailSentEvent::class, $mail, 'The mail.sent Event did not run');
    }

    public function testOrderStateTransition(): void
    {
        $orderId = $this->performOrder();

        $this->orderService->orderStateTransition($orderId, 'cancel', new ParameterBag(), $this->channelContext->getContext());

        $criteria = new Criteria([$orderId]);

        $criteria->addAssociation('stateMachineState');

        $cancelledOrder = $this->orderRepository->search($criteria, $this->channelContext->getContext())->getEntities()->first();
        static::assertNotNull($cancelledOrder);
        $state = $cancelledOrder->getStateMachineState();

        static::assertNotNull($state);
        static::assertSame('cancelled', $state->getTechnicalName());
    }

    public function testOrderStateTransitionSendsMail(): void
    {
        if (!static::getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without frontend in NEXT-16882');
        }

        $orderId = $this->performOrder();

        $domain = 'http://contena.' . Uuid::randomHex();
        $this->setDomainForChannel($domain, Defaults::LANGUAGE_SYSTEM);

        $criteria = new Criteria([$orderId]);

        $criteria->addAssociation('stateMachineState');

        $order = $this->orderRepository->search($criteria, $this->channelContext->getContext())->getEntities()->first();
        static::assertNotNull($order);

        $url = $domain . '/account/order/' . $order->getDeepLinkCode();
        $mail = null;
        $this->catchEvent(MailSentEvent::class, $mail);

        $this->orderService->orderStateTransition($orderId, 'cancel', new ParameterBag(), $this->channelContext->getContext());
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        static::assertInstanceOf(MailSentEvent::class, $mail, 'The mail.sent Event did not run');
        $htmlText = $mail->getContents()['text/html'];
        static::assertIsString($htmlText);
        static::assertStringContainsString('The new status is as follows: Cancelled.', $htmlText);
        static::assertStringContainsString($url, $htmlText);
    }

    public function testMailTemplateHasCorrectDomain(): void
    {
        if (!static::getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without frontend in NEXT-16882');
        }

        $data = new RequestDataBag(['tos' => true]);
        $this->fillCart($this->channelContext->getToken());

        $firstDomain = 'http://contena.first-domain';
        $this->setDomainForChannel($firstDomain, Defaults::LANGUAGE_SYSTEM);

        $languageRepository = static::getContainer()->get('language.repository');

        $criteria = new Criteria();
        $criteria->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [
                    new EqualsFilter('id', Defaults::LANGUAGE_SYSTEM),
                ]
            )
        );

        $languageId = $languageRepository->searchIds($criteria, $this->channelContext->getContext())->firstId();
        static::assertNotNull($languageId);
        $secondDomain = 'http://contena.second-domain';
        $this->setDomainForChannel($secondDomain, $languageId);

        $mail = null;
        $this->catchEvent(MailSentEvent::class, $mail);

        $this->orderService->createOrder($data, $this->channelContext);
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        static::assertInstanceOf(MailSentEvent::class, $mail, 'The mail.sent Event did not run');
        $htmlText = $mail->getContents()['text/html'];
        static::assertIsString($htmlText);
        static::assertStringContainsString($firstDomain, $htmlText);
        static::assertStringNotContainsString($secondDomain, $htmlText);
    }

    public function testMailTemplateHandlesVirtualDomains(): void
    {
        if (!static::getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without frontend in NEXT-16882');
        }

        $data = new RequestDataBag(['tos' => true]);
        $this->fillCart($this->channelContext->getToken());

        $domain = 'http://contena.test/virtual-domain';
        $this->setDomainForChannel($domain, Defaults::LANGUAGE_SYSTEM);

        $url = $domain . '/account/order';
        $mail = null;
        $this->catchEvent(MailSentEvent::class, $mail);

        $this->orderService->createOrder($data, $this->channelContext);
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        static::assertInstanceOf(MailSentEvent::class, $mail, 'The mail.sent Event did not run');
        $htmlText = $mail->getContents()['text/html'];
        static::assertIsString($htmlText);
        static::assertStringContainsString($url, $htmlText);
    }

    private function performOrder(): string
    {
        $data = new RequestDataBag(['tos' => true]);
        $this->fillCart($this->channelContext->getToken());

        return $this->orderService->createOrder($data, $this->channelContext);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createUser(string $firstName, string $lastName, array $options = []): string
    {
        $userId = Uuid::randomHex();
        $salutationId = $this->getValidSalutationId();

        $user = [
            'id' => $userId,
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => [
                'id' => $userId,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'city' => 'Schöppingen',
                'street' => 'Ebbinghoff 10',
                'zipcode' => '48624',
                'salutationId' => $salutationId,
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $userId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'salutationId' => $salutationId,
            'userNumber' => '12345',
        ];

        $user = array_merge_recursive($user, $options);

        static::getContainer()->get('user.repository')->create([$user], Context::createDefaultContext());

        return $userId;
    }

    private function fillCart(string $contextToken): void
    {
        $cart = static::getContainer()->get(CartService::class)->createNew($contextToken);

        $blogId = $this->createBlog();
        $cart->add(new LineItem('lineItem1', LineItem::BLOG_LINE_ITEM_TYPE, $blogId));
        $cart->setTransactions($this->createTransaction());
    }

    private function createBlog(): string
    {
        $blogId = Uuid::randomHex();

        $blog = [
            'id' => $blogId,
            'name' => 'Test blog',
            'blogNumber' => '123456789',
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 19.99, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['id' => $blogId, 'name' => 'Contena'],
            'tax' => ['id' => $this->getValidTaxId(), 'name' => 'testTaxRate', 'taxRate' => 15],
            'categories' => [
                ['id' => $blogId, 'name' => 'Test category'],
            ],
            'visibilities' => [
                [
                    'id' => $blogId,
                    'channelId' => TestDefaults::CHANNEL,
                    'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        static::getContainer()->get('blog.repository')->create([$blog], Context::createDefaultContext());

        return $blogId;
    }

    private function createTransaction(): TransactionCollection
    {
        return new TransactionCollection([
            new Transaction(
                new CalculatedPrice(
                    13.37,
                    13.37,
                    new CalculatedTaxCollection(),
                    new TaxRuleCollection()
                ),
                $this->getValidPaymentMethodId()
            ),
        ]);
    }

    private function setDomainForChannel(string $domain, string $languageId): void
    {
        $channelRepository = static::getContainer()->get('channel.repository');

        $data = [
            'id' => TestDefaults::CHANNEL,
            'domains' => [[
                'languageId' => $languageId,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => $domain,
            ]],
        ];

        $channelRepository->update([$data], $this->channelContext->getContext());
    }

    private function cleanDefaultChannelDomain(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $connection->delete(ChannelDomainDefinition::ENTITY_NAME, [
            'channel_id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL),
        ]);
    }
}
