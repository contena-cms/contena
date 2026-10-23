<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Event\AfterLineItemAddedEvent;
use Contena\Core\Checkout\Cart\Event\AfterLineItemQuantityChangedEvent;
use Contena\Core\Checkout\Cart\Event\AfterLineItemRemovedEvent;
use Contena\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent;
use Contena\Core\Checkout\Cart\Event\BeforeLineItemQuantityChangedEvent;
use Contena\Core\Checkout\Cart\Event\BeforeLineItemRemovedEvent;
use Contena\Core\Checkout\Cart\Event\CartCreatedEvent;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItemFactoryHandler\BlogLineItemFactory;
use Contena\Core\Checkout\Cart\PriceDefinitionFactory;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\Channel\AccountService;
use Contena\Core\Content\Flow\Dispatching\BufferedFlowExecutor;
use Contena\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\CountryAddToChannelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\TaxAddToChannelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseHelper\CallableClass;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Controller\AccountOrderController;

/**
 * @internal
 */
class CartServiceTest extends TestCase
{
    use CountryAddToChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use MailTemplateTestBehaviour;
    use TaxAddToChannelTestBehaviour;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    private AccountService $accountService;

    private Connection $connection;

    private string $blogId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = static::getContainer()->get(Connection::class);
        $this->userRepository = static::getContainer()->get('user.repository');
        $this->accountService = static::getContainer()->get(AccountService::class);

        $context = Context::createDefaultContext();
        $this->blogId = Uuid::randomHex();
        $blog = [
            'id' => $this->blogId,
            'blogNumber' => $this->blogId,
            'name' => 'test',
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 18],
            'manufacturer' => ['name' => 'test'],
            'active' => true,
            'visibilities' => [
                ['channelId' => TestDefaults::CHANNEL, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        static::getContainer()->get('blog.repository')
            ->create([$blog], $context);
    }

    public function testCreateNewWithEvent(): void
    {
        $caughtEvent = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(CartCreatedEvent::class, static function (CartCreatedEvent $event) use (&$caughtEvent): void {
            $caughtEvent = $event;
        });

        $cartService = static::getContainer()->get(CartService::class);

        $token = Uuid::randomHex();
        $newCart = $cartService->createNew($token);

        static::assertInstanceOf(CartCreatedEvent::class, $caughtEvent);
        static::assertSame($newCart, $caughtEvent->getCart());
        static::assertSame($newCart, $cartService->getCart($token, $this->getChannelContext()));
        static::assertNotSame($newCart, $cartService->createNew($token));
    }

    public function testLineItemAddedEventFired(): void
    {
        $isMerged = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(BeforeLineItemAddedEvent::class, static function (BeforeLineItemAddedEvent $addedEvent) use (&$isMerged): void {
            $isMerged = $addedEvent->isMerged();
        });

        $cartService = static::getContainer()->get(CartService::class);

        $context = $this->getChannelContext();

        $cartId = Uuid::randomHex();
        $cart = $cartService->getCart($cartId, $context);
        $cartService->add(
            $cart,
            (new LineItem('test', 'test'))->setStackable(true),
            $context
        );

        static::assertNotNull($isMerged);
        static::assertFalse($isMerged);

        $cartService->add(
            $cart,
            new LineItem('test', 'test'),
            $context
        );

        /** @phpstan-ignore staticMethod.impossibleType ($isMerged modified by listener) */
        static::assertTrue($isMerged);
    }

    public function testAfterLineItemAddedEventFired(): void
    {
        $listener = $this->createMock(CallableClass::class);
        $listener->expects($this->once())->method('__invoke');

        EventHookDispatcher::fromContainer(static::getContainer())->on(AfterLineItemAddedEvent::class, $listener);

        $cartService = static::getContainer()->get(CartService::class);

        $context = $this->getChannelContext();

        $cartId = Uuid::randomHex();
        $cart = $cartService->getCart($cartId, $context);
        $cartService->add(
            $cart,
            new LineItem('test', 'test'),
            $context
        );
    }

    public function testLineItemRemovedEventFired(): void
    {
        $listener = $this->createMock(CallableClass::class);
        $listener->expects($this->once())->method('__invoke');

        EventHookDispatcher::fromContainer(static::getContainer())->on(BeforeLineItemRemovedEvent::class, $listener);

        $cartService = static::getContainer()->get(CartService::class);

        $context = $this->getChannelContext();

        $lineItem = (new BlogLineItemFactory(new PriceDefinitionFactory()))->create(['id' => $this->blogId, 'referencedId' => $this->blogId], $context);

        $cart = $cartService->getCart($context->getToken(), $context);

        $cart = $cartService->add($cart, $lineItem, $context);

        static::assertTrue($cart->has($this->blogId));

        $cart = $cartService->remove($cart, $this->blogId, $context);

        static::assertFalse($cart->has($this->blogId));
    }

    public function testAfterLineItemRemovedEventFired(): void
    {
        $listener = $this->createMock(CallableClass::class);
        $listener->expects($this->once())->method('__invoke');

        EventHookDispatcher::fromContainer(static::getContainer())->on(AfterLineItemRemovedEvent::class, $listener);

        $cartService = static::getContainer()->get(CartService::class);

        $context = $this->getChannelContext();

        $lineItem = (new BlogLineItemFactory(new PriceDefinitionFactory()))->create(['id' => $this->blogId, 'referencedId' => $this->blogId], $context);

        $cart = $cartService->getCart($context->getToken(), $context);

        $cart = $cartService->add($cart, $lineItem, $context);

        static::assertTrue($cart->has($this->blogId));

        $cart = $cartService->remove($cart, $this->blogId, $context);

        static::assertFalse($cart->has($this->blogId));
    }

    public function testLineItemQuantityChangedEventFired(): void
    {
        $listener = $this->createMock(CallableClass::class);
        $listener->expects($this->once())->method('__invoke');

        EventHookDispatcher::fromContainer(static::getContainer())->on(BeforeLineItemQuantityChangedEvent::class, $listener);

        $cartService = static::getContainer()->get(CartService::class);

        $context = $this->getChannelContext();

        $lineItem = (new BlogLineItemFactory(new PriceDefinitionFactory()))->create(['id' => $this->blogId, 'referencedId' => $this->blogId], $context);

        $cart = $cartService->getCart($context->getToken(), $context);

        $cart = $cartService->add($cart, $lineItem, $context);

        static::assertTrue($cart->has($this->blogId));

        $cartService->changeQuantity($cart, $this->blogId, 100, $context);
    }

    public function testAfterLineItemQuantityChangedEventFired(): void
    {
        $listener = $this->createMock(CallableClass::class);
        $listener->expects($this->once())->method('__invoke');

        EventHookDispatcher::fromContainer(static::getContainer())->on(AfterLineItemQuantityChangedEvent::class, $listener);

        $cartService = static::getContainer()->get(CartService::class);

        $context = $this->getChannelContext();

        $lineItem = (new BlogLineItemFactory(new PriceDefinitionFactory()))->create(['id' => $this->blogId, 'referencedId' => $this->blogId], $context);

        $cart = $cartService->getCart($context->getToken(), $context);

        $cart = $cartService->add($cart, $lineItem, $context);

        static::assertTrue($cart->has($this->blogId));

        $cartService->changeQuantity($cart, $this->blogId, 100, $context);
    }

    public function testLineItemAddAndUpdate(): void
    {
        $cartService = static::getContainer()->get(CartService::class);

        $context = $this->getChannelContext();

        $blogId = Uuid::randomHex();
        $blog = [
            'id' => $blogId,
            'blogNumber' => $blogId,
            'name' => 'test',
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 5, 'net' => 5, 'linked' => false],
            ],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 18],
            'manufacturer' => ['name' => 'test'],
            'active' => true,
            'visibilities' => [
                ['channelId' => TestDefaults::CHANNEL, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        static::getContainer()->get('blog.repository')
            ->create([$blog], $context->getContext());
        $this->addTaxDataToChannel($context, $blog['tax']);

        $lineItem = (new BlogLineItemFactory(new PriceDefinitionFactory()))->create(['id' => $blogId, 'referencedId' => $blogId], $context);
        $cart = $cartService->getCart($context->getToken(), $context);
        $cart = $cartService->add($cart, $lineItem, $context);

        $lineItem = $cart->getLineItems()->get($blogId);

        static::assertInstanceOf(LineItem::class, $lineItem);
        static::assertSame(1, $lineItem->getQuantity());
        static::assertTrue($lineItem->isStackable());
        static::assertTrue($lineItem->isRemovable());

        $cartService->update($cart, ['foo' => [
            'id' => $blogId,
            'quantity' => 20,
            'payload' => ['foo' => 'bar'],
            'stackable' => false,
            'removable' => false,
        ]], $context);

        static::assertSame(20, $lineItem->getQuantity());
        static::assertTrue($lineItem->isStackable());
        static::assertTrue($lineItem->isRemovable());
        static::assertSame('bar', $lineItem->getPayloadValue('foo'));
    }

    public function testRemoveLineItems(): void
    {
        $cartService = static::getContainer()->get(CartService::class);

        $context = $this->getChannelContext();

        $blogId1 = Uuid::randomHex();
        $blogId2 = Uuid::randomHex();
        $blogId3 = Uuid::randomHex();

        $blogs = [];
        foreach ([$blogId1, $blogId2, $blogId3] as $blogId) {
            $blogs[] = [
                'id' => $blogId,
                'blogNumber' => $blogId,
                'name' => 'test',
                'stock' => 10,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 5, 'net' => 5, 'linked' => false],
                ],
                'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 18],
                'manufacturer' => ['name' => 'test'],
                'active' => true,
                'visibilities' => [
                    ['channelId' => TestDefaults::CHANNEL, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ];
        }

        static::getContainer()->get('blog.repository')
            ->create($blogs, $context->getContext());

        $lineItems = [];
        foreach ($blogs as $blog) {
            $this->addTaxDataToChannel($context, $blog['tax']);

            $lineItems[] = (new BlogLineItemFactory(new PriceDefinitionFactory()))->create(['id' => $blog['id'], 'referencedId' => $blog['id']], $context);
        }

        $cart = $cartService->getCart($context->getToken(), $context);
        $cart = $cartService->add($cart, $lineItems, $context);

        static::assertCount(3, $cart->getLineItems());

        $cart = $cartService->removeItems($cart, [
            $blogId1,
            $blogId2,
        ], $context);

        static::assertCount(1, $cart->getLineItems());

        $remainingLineItem = $cart->getLineItems()->get($blogId3);
        static::assertInstanceOf(LineItem::class, $remainingLineItem);
        static::assertSame($blogId3, $remainingLineItem->getReferencedId());
    }

    public function testZeroPricedItemsCanBeAddedToCart(): void
    {
        $cartService = static::getContainer()->get(CartService::class);

        $context = $this->getChannelContext();

        $blogId = Uuid::randomHex();
        $blog = [
            'id' => $blogId,
            'blogNumber' => $blogId,
            'name' => 'test',
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 0, 'net' => 0, 'linked' => false],
            ],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 18],
            'manufacturer' => ['name' => 'test'],
            'active' => true,
            'visibilities' => [
                ['channelId' => TestDefaults::CHANNEL, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        static::getContainer()->get('blog.repository')
            ->create([$blog], $context->getContext());
        $this->addTaxDataToChannel($context, $blog['tax']);

        $lineItem = (new BlogLineItemFactory(new PriceDefinitionFactory()))->create(['id' => $blogId, 'referencedId' => $blogId], $context);

        $cart = $cartService->getCart($context->getToken(), $context);

        $cart = $cartService->add($cart, $lineItem, $context);

        static::assertTrue($cart->has($blogId));
        static::assertSame(0.0, $cart->getPrice()->getTotalPrice());

        $calculatedLineItem = $cart->getLineItems()->get($blogId);
        static::assertNotNull($calculatedLineItem);
        static::assertNotNull($calculatedLineItem->getPrice());
        static::assertSame(0.0, $calculatedLineItem->getPrice()->getTotalPrice());

        $calculatedTaxes = $calculatedLineItem->getPrice()->getCalculatedTaxes();
        static::assertNotNull($calculatedTaxes);
        static::assertSame(0.0, $calculatedTaxes->getAmount());
    }

    public function testOrderCartSendMail(): void
    {
        if (!static::getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without frontend in NEXT-16882');
        }

        $context = $this->getChannelContext();

        $contextService = static::getContainer()->get(ChannelContextService::class);

        $addressId = Uuid::randomHex();

        $mail = 'test@contena.cn';
        $password = 'contena';

        $this->createUser($addressId, $mail, $password, $context->getContext());

        $newtoken = $this->accountService->loginByCredentials($mail, $password, $context);

        $context = $contextService->get(new ChannelContextServiceParameters(TestDefaults::CHANNEL, $newtoken));

        $lineItem = (new BlogLineItemFactory(new PriceDefinitionFactory()))->create(['id' => $this->blogId, 'referencedId' => $this->blogId], $context);

        $cartService = static::getContainer()->get(CartService::class);

        $cart = $cartService->getCart($context->getToken(), $context);

        $cart = $cartService->add($cart, $lineItem, $context);

        $this->setDomainForChannel('http://contena.local', Defaults::LANGUAGE_SYSTEM, $context->getContext());

        $systemConfigService = static::getContainer()->get(SystemConfigService::class);

        $systemConfigService->set('core.basicInformation.email', 'test@example.org');

        $mail = null;
        $this->catchEvent(MailSentEvent::class, $mail);

        $cartService->order($cart, $context, new RequestDataBag());
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        static::assertInstanceOf(MailSentEvent::class, $mail, 'The mail.sent Event did not run');
        $htmlText = $mail->getContents()['text/html'];
        static::assertIsString($htmlText);
        static::assertStringContainsString('Shipping costs: €0.00', $htmlText);
    }

    public function testCartCreatedWithGivenToken(): void
    {
        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $context = $channelContextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $token = Uuid::randomHex();
        $cartService = static::getContainer()->get(CartService::class);
        $cart = $cartService->getCart($token, $context);

        static::assertSame($token, $cart->getToken());
    }

    private function createUser(string $addressId, string $mail, string $password, Context $context): void
    {
        $this->connection->executeStatement('DELETE FROM user WHERE email = :mail', [
            'mail' => $mail,
        ]);

        $user = [
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'firstName' => 'not',
                'lastName' => 'not',
                'street' => 'test',
                'city' => 'not',
                'zipcode' => 'not',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $mail,
            'password' => $password,
            'lastName' => 'not',
            'firstName' => 'match',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => 'not',
        ];

        $this->userRepository->create([$user], $context);
    }

    private function getChannelContext(): ChannelContext
    {
        $this->addCountriesToChannel();

        return static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);
    }

    private function setDomainForChannel(string $domain, string $languageId, Context $context): void
    {
        /** @var EntityRepository<ChannelCollection> $channelRepository */
        $channelRepository = static::getContainer()->get('channel.repository');

        try {
            $data = [
                'id' => TestDefaults::CHANNEL,
                'domains' => [
                    [
                        'languageId' => $languageId,
                        'currencyId' => Defaults::CURRENCY,
                        'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                        'url' => $domain,
                    ],
                ],
            ];

            $channelRepository->update([$data], $context);
        } catch (\Exception) {
            // ignore if domain already exists
        }
    }
}
