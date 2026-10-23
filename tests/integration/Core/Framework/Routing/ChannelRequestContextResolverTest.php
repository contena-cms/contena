<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\ChannelRequest;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\AdminChannelApiSource;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Routing\ChannelRequestContextResolver;
use Contena\Core\Framework\Routing\Event\ChannelContextResolvedEvent;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Currency\CurrencyCollection;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * @internal
 */
class ChannelRequestContextResolverTest extends TestCase
{
    use UserTestTrait;
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<CurrencyCollection>
     */
    private EntityRepository $currencyRepository;

    private ChannelContextServiceInterface $contextService;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->currencyRepository = static::getContainer()->get('currency.repository');
        $this->contextService = static::getContainer()->get(ChannelContextService::class);
    }

    public function testRequestChannelCurrency(): void
    {
        $this->createTestChannel();
        $resolver = static::getContainer()->get(ChannelRequestContextResolver::class);

        $currencyId = $this->getCurrencyId('USD');

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, $this->ids->get('sales-channel'));
        $request->attributes->set(ChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID, $currencyId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);

        $resolvedContext = null;
        $listenerContextEventClosure = static function (ChannelContextResolvedEvent $event) use (&$resolvedContext): void {
            $resolvedContext = $event->getChannelContext();
        };

        EventHookDispatcher::fromContainer(static::getContainer())->on(ChannelContextResolvedEvent::class, $listenerContextEventClosure);

        $resolver->resolve($request);

        static::assertInstanceOf(ChannelContext::class, $resolvedContext, 'The "' . ChannelContextResolvedEvent::class . '" Event did not run');
        static::assertSame($currencyId, $resolvedContext->getContext()->getCurrencyId());
        static::assertInstanceOf(ChannelApiSource::class, $resolvedContext->getContext()->getSource());
    }

    public function testRequestChannelCurrencyDoesNotOverwriteContextData(): void
    {
        $this->createTestChannel();

        $eurCurrencyId = $this->getCurrencyId('EUR');
        $contextPersister = static::getContainer()->get(ChannelContextPersister::class);
        $contextPersister->save($this->ids->get('token'), [ChannelContextService::CURRENCY_ID => $eurCurrencyId], $this->ids->get('sales-channel'));

        $resolver = static::getContainer()->get(ChannelRequestContextResolver::class);

        $currencyId = $this->getCurrencyId('USD');

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, $this->ids->get('sales-channel'));
        $request->attributes->set(ChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID, $currencyId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $this->ids->get('token'));

        $resolvedContext = null;
        $listenerContextEventClosure = static function (ChannelContextResolvedEvent $event) use (&$resolvedContext): void {
            $resolvedContext = $event->getChannelContext();
        };

        EventHookDispatcher::fromContainer(static::getContainer())->on(ChannelContextResolvedEvent::class, $listenerContextEventClosure);

        $resolver->resolve($request);

        static::assertInstanceOf(ChannelContext::class, $resolvedContext, 'The "' . ChannelContextResolvedEvent::class . '" Event did not run');
        static::assertSame($eurCurrencyId, $resolvedContext->getContext()->getCurrencyId());
        static::assertInstanceOf(ChannelApiSource::class, $resolvedContext->getContext()->getSource());
    }

    public function testRequestCurrencyHeaderDoesOverwriteContextData(): void
    {
        $this->createTestChannel();

        $eurCurrencyId = $this->getCurrencyId('EUR');
        $contextPersister = static::getContainer()->get(ChannelContextPersister::class);
        $contextPersister->save($this->ids->get('token'), [ChannelContextService::CURRENCY_ID => $eurCurrencyId], $this->ids->get('sales-channel'));

        $resolver = static::getContainer()->get(ChannelRequestContextResolver::class);

        $currencyId = $this->getCurrencyId('USD');

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, $this->ids->get('sales-channel'));
        $request->headers->set(PlatformRequest::HEADER_CURRENCY_ID, $currencyId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $this->ids->get('token'));

        $resolvedContext = null;
        $listenerContextEventClosure = static function (ChannelContextResolvedEvent $event) use (&$resolvedContext): void {
            $resolvedContext = $event->getChannelContext();
        };

        EventHookDispatcher::fromContainer(static::getContainer())->on(ChannelContextResolvedEvent::class, $listenerContextEventClosure);

        $resolver->resolve($request);

        static::assertInstanceOf(ChannelContext::class, $resolvedContext, 'The "' . ChannelContextResolvedEvent::class . '" Event did not run');
        static::assertSame($currencyId, $resolvedContext->getContext()->getCurrencyId());
        static::assertInstanceOf(ChannelApiSource::class, $resolvedContext->getContext()->getSource());
    }

    #[DataProvider('domainData')]
    public function testContextCurrency(string $currencyCode): void
    {
        $this->createTestChannel();
        $currencyId = $this->getCurrencyId($currencyCode);

        $context = $this->contextService->get(
            new ChannelContextServiceParameters($this->ids->get('sales-channel'), $this->ids->get('token'), Defaults::LANGUAGE_SYSTEM, $currencyId)
        );

        static::assertSame($currencyId, $context->getContext()->getCurrencyId());
    }

    /**
     * @return list<array{0: string}>
     */
    public static function domainData(): array
    {
        return [
            [
                'EUR',
            ],
            [
                'USD',
            ],
        ];
    }

    /**
     * @param array<string, bool> $attributes
     */
    #[DataProvider('loginRequiredAnnotationData')]
    public function testLoginRequiredAnnotation(bool $doLogin, bool $isGuest, array $attributes, bool $pass): void
    {
        $resolver = static::getContainer()->get(ChannelRequestContextResolver::class);

        $currencyId = $this->getCurrencyId('USD');

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, TestDefaults::CHANNEL);
        $request->attributes->set(ChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID, $currencyId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);

        if ($doLogin) {
            $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $this->loginUser($isGuest));
        }

        foreach ($attributes as $k => $v) {
            $request->attributes->set($k, $v);
        }

        $exception = null;

        try {
            $resolver->resolve($request);
        } catch (\Exception $e) {
            $exception = $e;
        }

        if ($pass) {
            static::assertNull($exception, 'Exception: ' . ($exception !== null ? \print_r($exception->getMessage(), true) : 'No Exception'));
        } else {
            static::assertInstanceOf(RoutingException::class, $exception, 'Exception: ' . ($exception !== null ? \print_r($exception->getMessage(), true) : 'No Exception'));
        }
    }

    public function testRequestAdminChannelApiSource(): void
    {
        $this->createTestChannel();
        $resolver = static::getContainer()->get(ChannelRequestContextResolver::class);

        $currencyId = $this->getCurrencyId('USD');

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, $this->ids->get('sales-channel'));
        $request->attributes->set(ChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID, $currencyId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, Context::createDefaultContext());

        $resolvedContext = null;
        $listenerContextEventClosure = static function (ChannelContextResolvedEvent $event) use (&$resolvedContext): void {
            $resolvedContext = $event->getChannelContext();
        };

        EventHookDispatcher::fromContainer(static::getContainer())->on(ChannelContextResolvedEvent::class, $listenerContextEventClosure);

        $resolver->resolve($request);

        static::assertInstanceOf(ChannelContext::class, $resolvedContext, 'The "' . ChannelContextResolvedEvent::class . '" Event did not run');
        static::assertSame($currencyId, $resolvedContext->getContext()->getCurrencyId());
        static::assertInstanceOf(AdminChannelApiSource::class, $resolvedContext->getContext()->getSource());
    }

    public function testImitatingUserIdWithUser(): void
    {
        $resolver = static::getContainer()->get(ChannelRequestContextResolver::class);

        $currencyId = $this->getCurrencyId('USD');

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, TestDefaults::CHANNEL);
        $request->attributes->set(ChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID, $currencyId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $this->loginUser(false));

        $request->setSession(new Session(new MockArraySessionStorage()));
        $imitatingUserId = Uuid::randomHex();
        $request->getSession()->set(PlatformRequest::ATTRIBUTE_IMITATING_USER_ID, $imitatingUserId);

        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push($request);

        $resolver->resolve($request);

        static::assertSame($imitatingUserId, $request->getSession()->get(PlatformRequest::ATTRIBUTE_IMITATING_USER_ID));

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);

        static::assertInstanceOf(ChannelContext::class, $context);
        static::assertSame($imitatingUserId, $context->getImitatingUserId());
    }

    public function testImitatingUserIdClearWithoutUser(): void
    {
        $resolver = static::getContainer()->get(ChannelRequestContextResolver::class);

        $currencyId = $this->getCurrencyId('USD');

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, TestDefaults::CHANNEL);
        $request->attributes->set(ChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID, $currencyId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);

        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->getSession()->set(PlatformRequest::ATTRIBUTE_IMITATING_USER_ID, Uuid::randomHex());

        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push($request);

        $resolver->resolve($request);

        static::assertNull($request->getSession()->get(PlatformRequest::ATTRIBUTE_IMITATING_USER_ID));

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);

        static::assertInstanceOf(ChannelContext::class, $context);
        static::assertNull($context->getImitatingUserId());
    }

    /**
     * @return list<array{0: bool, 1: bool, 2: array<string, bool>, 3: bool}>
     */
    public static function loginRequiredAnnotationData(): array
    {
        $loginRequiredNotAllowGuest = [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true];
        $loginRequiredAllowGuest = [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true, PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true];

        return [
            [
                true, // login
                true, // guest
                $loginRequiredNotAllowGuest, // annotation
                false, // pass
            ],
            [
                true,
                false,
                $loginRequiredNotAllowGuest,
                true,
            ],
            [
                false,
                false,
                $loginRequiredNotAllowGuest,
                false,
            ],
            [
                false,
                true,
                $loginRequiredNotAllowGuest,
                false,
            ],
            [
                true,
                true,
                $loginRequiredAllowGuest,
                true,
            ],
            [
                true,
                false,
                $loginRequiredAllowGuest,
                true,
            ],
            [
                false,
                false,
                $loginRequiredAllowGuest,
                false,
            ],
            [
                false,
                true,
                $loginRequiredAllowGuest,
                false,
            ],

            [
                true,
                false,
                [],
                true,
            ],
            [
                false,
                false,
                [],
                true,
            ],
            [
                true,
                true,
                [],
                true,
            ],
            [
                false,
                true,
                [],
                true,
            ],
        ];
    }

    private function loginUser(bool $isGuest): string
    {
        $email = Uuid::randomHex() . '@example.com';
        $userId = $this->createUser($email, $isGuest);

        $token = Random::getAlphanumericString(32);
        static::getContainer()->get(ChannelContextPersister::class)->save($token, ['userId' => $userId], TestDefaults::CHANNEL);

        return $token;
    }

    private function getCurrencyId(string $isoCode): ?string
    {
        return $this->currencyRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('isoCode', $isoCode)),
            Context::createDefaultContext()
        )->firstId();
    }

    private function createTestChannel(): void
    {
        $usdCurrencyId = $this->getCurrencyId('USD');

        $this->createChannel([
            'id' => $this->ids->create('sales-channel'),
            'currencies' => [
                ['id' => Defaults::CURRENCY],
                ['id' => $usdCurrencyId],
            ],
            'domains' => [
                [
                    'id' => $this->ids->get('eur-domain'),
                    'url' => 'http://test.store/en-eur',
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                ],
                [
                    'id' => $this->ids->get('usd-domain'),
                    'url' => 'http://test.store/en-usd',
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => $usdCurrencyId,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                ],
            ],
        ]);
    }
}
