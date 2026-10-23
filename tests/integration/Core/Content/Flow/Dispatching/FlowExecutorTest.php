<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Flow\Dispatching;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\LineItemFactoryHandler\BlogLineItemFactory;
use Contena\Core\Checkout\Cart\PriceDefinitionFactory;
use Contena\Core\Checkout\Cart\Rule\CartVolumeRule;
use Contena\Core\Checkout\Cart\Rule\LineItemRule;
use Contena\Core\Checkout\Cart\Rule\LineItemTotalPriceRule;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Event\UserDoubleOptInRegistrationEvent;
use Contena\Core\System\User\Rule\UserGroupRule;
use Contena\Core\System\User\Rule\LastNameRule;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Checkout\Order\OrderDefinition;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Content\Flow\Dispatching\Action\AddUserTagAction;
use Contena\Core\Content\Flow\Dispatching\Action\AddOrderTagAction;
use Contena\Core\Content\Flow\Dispatching\BufferedFlowExecutor;
use Contena\Core\Content\Flow\Dispatching\FlowDispatcher;
use Contena\Core\Content\Flow\FlowCollection;
use Contena\Core\Content\Flow\Rule\OrderTagRule;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Tag\TagCollection;
use Contena\Core\Test\Integration\Builder\User\UserBuilder;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class FlowExecutorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private CartService $cartService;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    /**
     * @var EntityRepository<OrderTransactionCollection>
     */
    private EntityRepository $orderTransactionRepository;

    /**
     * @var EntityRepository<FlowCollection>
     */
    private EntityRepository $flowRepository;

    /**
     * @var EntityRepository<TagCollection>
     */
    private EntityRepository $tagRepository;

    private OrderTransactionStateHandler $orderTransactionStateHandler;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    private FlowDispatcher $flowDispatcher;

    private ChannelContext $channelContext;

    private string $userId;

    protected function setUp(): void
    {
        $this->cartService = static::getContainer()->get(CartService::class);
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->orderTransactionRepository = static::getContainer()->get('order_transaction.repository');
        $this->orderTransactionStateHandler = static::getContainer()->get(OrderTransactionStateHandler::class);
        $this->flowRepository = static::getContainer()->get('flow.repository');
        $this->tagRepository = static::getContainer()->get('tag.repository');
        $this->userRepository = static::getContainer()->get('user.repository');
        $this->flowDispatcher = static::getContainer()->get(FlowDispatcher::class);
        $this->userId = $this->createUser();
        $this->channelContext = $this->createDefaultChannelContext();
    }

    public function testFlowExecutesWithIfSequencesEvaluated(): void
    {
        $ids = new IdsCollection();

        $this->createTags($ids);

        $this->createFlow($ids);

        $this->placeOrder($ids);

        $this->orderRepository->update([
            [
                'id' => $ids->get('order'),
                'tags' => [
                    ['id' => $ids->get('tag-1')],
                ],
            ],
        ], $this->channelContext->getContext());

        $blog = (new BlogBuilder($ids, 'blog'))->price(50)->build();

        unset($blog['type']);

        $this->blogRepository->update([$blog], $this->channelContext->getContext());

        $this->changeTransactionStateToPaid($ids->get('order'));

        $criteria = new Criteria([$ids->get('order')]);
        $criteria->addAssociation('tags');

        $order = $this->orderRepository
            ->search($criteria, $this->channelContext->getContext())
            ->getEntities()->first();

        static::assertInstanceOf(OrderEntity::class, $order);
        static::assertInstanceOf(TagCollection::class, $order->getTags());
        static::assertContains($ids->get('tag-1'), $order->getTags()->getIds());
        static::assertContains($ids->get('tag-2'), $order->getTags()->getIds());
    }

    public function testUserAwareFlowExecutesWithIfSequencesEvaluated(): void
    {
        $ids = new IdsCollection();

        $this->createTags($ids);

        $this->createUserAwareFlow($ids);

        $this->dispatchUserDoubleOptInRegistrationEvent($ids);

        $criteria = new Criteria([$ids->get('user-1')]);
        $criteria->addAssociation('tags');

        $user = $this->userRepository
            ->search($criteria, $this->channelContext->getContext())
            ->getEntities()->first();

        static::assertInstanceOf(UserEntity::class, $user);
        static::assertInstanceOf(TagCollection::class, $user->getTags());
        static::assertContains($ids->get('tag-1'), $user->getTags()->getIds());
    }

    private function placeOrder(IdsCollection $ids): void
    {
        $cart = $this->cartService->createNew($this->channelContext->getToken());
        $cart = $this->addBlogs($cart, $ids);

        $ids->set('order', $this->cartService->order($cart, $this->channelContext, new RequestDataBag()));
    }

    private function addBlogs(Cart $cart, IdsCollection $ids): Cart
    {
        $taxIds = $this->channelContext->getTaxRules()->getIds();
        $ids->set('t1', (string) array_pop($taxIds));

        $this->blogRepository->create([
            (new BlogBuilder($ids, 'blog'))
                ->price(100)
                ->tax('t1')
                ->visibility()
                ->add('height', 3000)
                ->add('width', 3000)
                ->add('length', 3000)
                ->build(),
        ], $this->channelContext->getContext());

        return $this->addBlogToCart($ids->get('blog'), 1, $cart, $this->cartService, $this->channelContext);
    }

    private function changeTransactionStateToPaid(string $orderId): void
    {
        $transaction = $this->orderTransactionRepository
            ->search(
                (new Criteria())
                    ->addFilter(new EqualsFilter('orderId', $orderId))
                    ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING)),
                $this->channelContext->getContext()
            )->getEntities()->first();
        static::assertInstanceOf(OrderTransactionEntity::class, $transaction);

        $this->orderTransactionStateHandler->paid($transaction->getId(), $this->channelContext->getContext());
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();
    }

    private function createTags(IdsCollection $idsCollection): void
    {
        $tags = [
            [
                'id' => $idsCollection->get('tag-1'),
                'name' => 'foo',
            ],
            [
                'id' => $idsCollection->get('tag-2'),
                'name' => 'bar',
            ],
        ];

        $this->tagRepository->create($tags, $this->channelContext->getContext());
    }

    private function createFlow(IdsCollection $idsCollection): void
    {
        $this->flowRepository->create([
            [
                'name' => 'On enter paid state',
                'eventName' => 'state_enter.order_transaction.state.paid',
                'priority' => 10,
                'active' => true,
                'sequences' => [
                    [
                        'id' => $idsCollection->get('sequence-1'),
                        'parentId' => null,
                        'actionName' => null,
                        'config' => [],
                        'position' => 1,
                        'rule' => [
                            'name' => 'Test order rule',
                            'priority' => 1,
                            'conditions' => [
                                [
                                    'type' => (new OrderTagRule())->getName(),
                                    'value' => [
                                        'identifiers' => [$idsCollection->get('tag-1')],
                                        'operator' => OrderTagRule::OPERATOR_EQ,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => $idsCollection->get('sequence-2'),
                        'parentId' => $idsCollection->get('sequence-1'),
                        'actionName' => null,
                        'config' => [],
                        'position' => 1,
                        'trueCase' => true,
                        'rule' => [
                            'name' => 'Test line item rule',
                            'priority' => 1,
                            'conditions' => [
                                [
                                    'type' => (new LineItemRule())->getName(),
                                    'value' => [
                                        'identifiers' => [$idsCollection->get('blog')],
                                        'operator' => OrderTagRule::OPERATOR_EQ,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => $idsCollection->get('sequence-3'),
                        'parentId' => $idsCollection->get('sequence-2'),
                        'actionName' => null,
                        'config' => [],
                        'position' => 1,
                        'trueCase' => true,
                        'rule' => [
                            'name' => 'Test user rule',
                            'priority' => 1,
                            'conditions' => [
                                [
                                    'type' => (new LastNameRule())->getName(),
                                    'value' => [
                                        'lastName' => 'Mustermann',
                                        'operator' => OrderTagRule::OPERATOR_EQ,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => $idsCollection->get('sequence-4'),
                        'parentId' => $idsCollection->get('sequence-3'),
                        'actionName' => null,
                        'config' => [],
                        'position' => 1,
                        'trueCase' => true,
                        'rule' => [
                            'name' => 'Test cart rule',
                            'priority' => 1,
                            'conditions' => [
                                [
                                    'type' => (new CartVolumeRule())->getName(),
                                    'value' => [
                                        'volume' => 8,
                                        'operator' => CartVolumeRule::OPERATOR_GT,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => $idsCollection->get('sequence-5'),
                        'parentId' => $idsCollection->get('sequence-4'),
                        'actionName' => null,
                        'config' => [],
                        'position' => 1,
                        'trueCase' => true,
                        'rule' => [
                            'name' => 'Test price rule',
                            'priority' => 1,
                            'conditions' => [
                                [
                                    'type' => (new LineItemTotalPriceRule())->getName(),
                                    'value' => [
                                        'amount' => 100,
                                        'operator' => LineItemTotalPriceRule::OPERATOR_GTE,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'parentId' => $idsCollection->get('sequence-5'),
                        'ruleId' => null,
                        'actionName' => AddOrderTagAction::getName(),
                        'config' => [
                            'tagIds' => [$idsCollection->get('tag-2') => 'bar'],
                            'entity' => OrderDefinition::ENTITY_NAME,
                        ],
                        'position' => 1,
                        'trueCase' => true,
                    ],
                ],
            ],
        ], $this->channelContext->getContext());
    }

    private function addBlogToCart(string $blogId, int $quantity, Cart $cart, CartService $cartService, ChannelContext $context): Cart
    {
        $factory = new BlogLineItemFactory(new PriceDefinitionFactory());
        $blog = $factory->create(['id' => $blogId, 'referencedId' => $blogId, 'quantity' => $quantity], $context);

        return $cartService->add($cart, $blog, $context);
    }

    private function createUserAwareFlow(IdsCollection $idsCollection): void
    {
        $this->flowRepository->create([
            [
                'name' => 'On user double opt in registration',
                'eventName' => UserDoubleOptInRegistrationEvent::EVENT_NAME,
                'priority' => 10,
                'active' => true,
                'sequences' => [
                    [
                        'id' => $idsCollection->get('sequence-1'),
                        'parentId' => null,
                        'actionName' => null,
                        'config' => [],
                        'position' => 1,
                        'rule' => [
                            'name' => 'Test user requested group rule',
                            'priority' => 1,
                            'conditions' => [
                                [
                                    'type' => (new UserGroupRule())->getName(),
                                    'value' => [
                                        'userGroupIds' => [$idsCollection->get('user-group')],
                                        'operator' => UserGroupRule::OPERATOR_EQ,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'parentId' => $idsCollection->get('sequence-1'),
                        'ruleId' => null,
                        'actionName' => AddUserTagAction::getName(),
                        'config' => [
                            'tagIds' => [$idsCollection->get('tag-1') => 'bar'],
                            'entity' => UserDefinition::ENTITY_NAME,
                        ],
                        'position' => 1,
                        'trueCase' => true,
                    ],
                ],
            ],
        ], $this->channelContext->getContext());
    }

    private function dispatchUserDoubleOptInRegistrationEvent(IdsCollection $ids): void
    {
        $channelContext = $this->createDefaultChannelContext(false);

        static::assertNull($channelContext->getUser());

        $user = (new UserBuilder(
            $ids,
            'user-1'
        ))->build();

        $this->userRepository->create([$user], $channelContext->getContext());

        $user = $this->userRepository->search(
            new Criteria([$ids->get('user-1')]),
            $channelContext->getContext()
        )->getEntities()->first();

        static::assertInstanceOf(UserEntity::class, $user);

        $event = new UserDoubleOptInRegistrationEvent(
            $user,
            $channelContext,
            ''
        );

        $this->flowDispatcher->dispatch($event);
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();
    }

    private function createDefaultChannelContext(bool $withUser = true): ChannelContext
    {
        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);

        if ($withUser === false) {
            return $channelContextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL);
        }

        return $channelContextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL, [ChannelContextService::CUSTOMER_ID => $this->userId]);
    }
}
