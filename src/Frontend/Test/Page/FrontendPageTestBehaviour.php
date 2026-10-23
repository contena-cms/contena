<?php declare(strict_types=1);

namespace Contena\Frontend\Test\Page;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Struct\Struct;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Page\PageLoadedEvent;
use Contena\Frontend\Pagelet\PageletLoadedEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
trait FrontendPageTestBehaviour
{
    use TaxAddToChannelTestBehaviour;

    /**
     * @template TEvent of PageLoadedEvent
     *
     * @param class-string<TEvent> $expectedClass
     * @param TEvent|null $event
     */
    public static function assertPageEvent(
        string $expectedClass,
        ?PageLoadedEvent $event,
        ChannelContext $channelContext,
        Request $request,
        Struct $page
    ): void {
        TestCase::assertInstanceOf($expectedClass, $event);
        TestCase::assertSame($channelContext, $event->getChannelContext());
        TestCase::assertSame($channelContext->getContext(), $event->getContext());
        TestCase::assertSame($request, $event->getRequest());
        TestCase::assertSame($page, $event->getPage());
    }

    /**
     * @template TEvent of PageletLoadedEvent
     *
     * @param class-string<TEvent> $expectedClass
     * @param TEvent $event
     */
    public static function assertPageletEvent(
        string $expectedClass,
        PageletLoadedEvent $event,
        ChannelContext $channelContext,
        Request $request,
        Struct $page
    ): void {
        TestCase::assertInstanceOf($expectedClass, $event);
        TestCase::assertSame($channelContext, $event->getChannelContext());
        TestCase::assertSame($channelContext->getContext(), $event->getContext());
        TestCase::assertSame($request, $event->getRequest());
        TestCase::assertSame($page, $event->getPagelet());
    }

    abstract protected function getPageLoader();

    protected function expectParamMissingException(string $paramName): void
    {
        $this->expectExceptionObject(RoutingException::missingRequestParameter($paramName));
    }

    protected function placeRandomOrder(ChannelContext $context): string
    {
        $blog = $this->getRandomBlog($context);

        $lineItem = (new LineItem($blog->getId(), LineItem::BLOG_LINE_ITEM_TYPE, $blog->getId()))
            ->setRemovable(true)
            ->setStackable(true);

        $cartService = static::getContainer()->get(CartService::class);
        $cart = $cartService->getCart($context->getToken(), $context);
        $cart->add($lineItem);

        return $cartService->order($cart, $context, new RequestDataBag());
    }

    /**
     * @param array<int|string, mixed> $config
     */
    protected function getRandomBlog(ChannelContext $context, ?int $stock = 1, ?bool $isCloseout = false, array $config = []): BlogEntity
    {
        $id = Uuid::randomHex();
        $blogNumber = Uuid::randomHex();
        $blogRepository = static::getContainer()->get('blog.repository');

        $data = [
            'id' => $id,
            'blogNumber' => $blogNumber,
            'stock' => $stock,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 15],
            'active' => true,
            'isCloseout' => $isCloseout,
            'categories' => [
                ['id' => Uuid::randomHex(), 'name' => 'asd'],
            ],
            'visibilities' => [
                ['channelId' => $context->getChannelId(), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $data = array_merge_recursive($data, $config);

        $blogRepository->create([$data], $context->getContext());
        $this->addTaxDataToChannel($context, $data['tax']);

        /** @var ChannelRepository<BlogCollection> $frontendBlogRepository */
        $frontendBlogRepository = static::getContainer()->get('channel.blog.repository');
        $blog = $frontendBlogRepository->search(new Criteria([$id]), $context)->getEntities()->first();
        static::assertNotNull($blog);

        return $blog;
    }

    protected function createChannelContextWithNavigation(): ChannelContext
    {
        $paymentMethodId = $this->getValidPaymentMethodId();
        $shippingMethodId = $this->getAvailableShippingMethod()->getId();
        $countryId = $this->getValidCountryId();
        $snippetSetId = $this->getSnippetSetIdForLocale('en-GB');
        $data = [
            'typeId' => Defaults::CHANNEL_TYPE_FRONTEND,
            'name' => 'store front',
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'snippetSetId' => $snippetSetId,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $paymentMethodId,
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $shippingMethodId,
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $countryId,
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'paymentMethods' => [['id' => $paymentMethodId]],
            'shippingMethods' => [['id' => $shippingMethodId]],
            'countries' => [['id' => $countryId]],
            'domains' => [
                [
                    'url' => 'http://test.com/' . Uuid::randomHex(),
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $snippetSetId,
                ],
            ],
        ];

        return $this->createContext($data, []);
    }

    protected function createChannelContextWithLoggedInUserAndWithNavigation(): ChannelContext
    {
        $paymentMethodId = $this->getValidPaymentMethodId();
        $shippingMethodId = $this->getAvailableShippingMethod()->getId();
        $countryId = $this->getValidCountryId();
        $snippetSetId = $this->getSnippetSetIdForLocale('en-GB');
        $data = [
            'typeId' => Defaults::CHANNEL_TYPE_FRONTEND,
            'name' => 'store front',
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'snippetSetId' => $snippetSetId,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $paymentMethodId,
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $shippingMethodId,
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'countryId' => $countryId,
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'paymentMethods' => [['id' => $paymentMethodId]],
            'shippingMethods' => [['id' => $shippingMethodId]],
            'countries' => [['id' => $countryId]],
            'domains' => [
                [
                    'url' => 'http://test.com/' . Uuid::randomHex(),
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $snippetSetId,
                ],
            ],
        ];

        return $this->createContext($data, [
            ChannelContextService::CUSTOMER_ID => $this->createUser()->getId(),
        ]);
    }

    /**
     * @param array<string, mixed>|null $channelData
     */
    protected function createChannelContext(?array $channelData = null): ChannelContext
    {
        $paymentMethodId = $this->getValidPaymentMethodId();
        $shippingMethodId = $this->getAvailableShippingMethod()->getId();
        $countryId = $this->getValidCountryId();
        $snippetSetId = $this->getSnippetSetIdForLocale('en-GB');
        $data = [
            'typeId' => Defaults::CHANNEL_TYPE_FRONTEND,
            'name' => 'store front',
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'snippetSetId' => $snippetSetId,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $paymentMethodId,
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $shippingMethodId,
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $countryId,
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'paymentMethods' => [['id' => $paymentMethodId]],
            'shippingMethods' => [['id' => $shippingMethodId]],
            'countries' => [['id' => $countryId]],
            'domains' => [
                [
                    'url' => 'http://test.com/' . Uuid::randomHex(),
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $snippetSetId,
                ],
            ],
        ];

        if ($channelData) {
            $data = array_merge($data, $channelData);
        }

        return $this->createContext($data, []);
    }

    /**
     * @template TEventName of Event
     *
     * @param class-string<TEventName> $eventName
     * @param TEventName|null $eventResult
     */
    protected function catchEvent(string $eventName, ?Event &$eventResult): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on($eventName, static function (Event $event) use (&$eventResult): void {
            $eventResult = $event;
        });
    }

    abstract protected static function getContainer(): ContainerInterface;

    private function createUser(): UserEntity
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
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
                'country' => ['id' => $this->getValidCountryId()],
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $userId . '@example.com',
            'password' => 'password',
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => $userId,
        ];

        /** @var EntityRepository<UserCollection> $repo */
        $repo = static::getContainer()->get('user.repository');

        $repo->create([$user], Context::createDefaultContext());

        $user = $repo->search(new Criteria([$userId]), Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($user);

        return $user;
    }

    /**
     * @param array<string, mixed> $channel
     * @param array<string, mixed> $options
     */
    private function createContext(array $channel, array $options): ChannelContext
    {
        $factory = static::getContainer()->get(ChannelContextFactory::class);
        $channelRepository = static::getContainer()->get('channel.repository');

        $channelId = Uuid::randomHex();
        $channel['id'] = $channelId;
        $channel['userGroupId'] = TestDefaults::FALLBACK_CUSTOMER_GROUP;

        $channelRepository->create([$channel], Context::createDefaultContext());

        $context = $factory->create(Uuid::randomHex(), $channelId, $options);

        $ruleLoader = static::getContainer()->get(CartRuleLoader::class);
        $ruleLoader->loadByToken($context, $context->getToken());

        return $context;
    }
}
