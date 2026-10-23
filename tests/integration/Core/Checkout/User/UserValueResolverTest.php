<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\UserValueResolver;
use Contena\Core\System\User\Exception\BadCredentialsException;
use Contena\Core\System\User\Channel\AccountService;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\ChannelRequestContextResolver;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\ChannelRequest;
use Contena\Core\System\Currency\CurrencyCollection;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Integration\Traits\UserTestTrait;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
class UserValueResolverTest extends TestCase
{
    use UserTestTrait;
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<CurrencyCollection>
     */
    private EntityRepository $currencyRepository;

    private AccountService $accountService;

    private ChannelContext $channelContext;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->currencyRepository = static::getContainer()->get('currency.repository');

        $this->createTestChannel();

        $this->accountService = static::getContainer()->get(AccountService::class);
        /** @var AbstractChannelContextFactory $channelContextFactory */
        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $this->channelContext = $channelContextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL);
    }

    #[DataProvider('loginRequiredAnnotationData')]
    public function testUserResolver(bool $loginRequired, bool $context, bool $pass): void
    {
        $resolver = static::getContainer()->get(UserValueResolver::class);

        $channelResolver = static::getContainer()->get(ChannelRequestContextResolver::class);

        $currencyId = $this->getCurrencyId('USD');

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, TestDefaults::CHANNEL);
        $request->attributes->set(ChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID, $currencyId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);
        $request->attributes->set('_route', 'test-route');

        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $this->loginUser());

        if ($loginRequired) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED, $loginRequired);
        }

        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push($request);

        if ($context) {
            $channelResolver->resolve($request);
        }

        $exception = null;

        try {
            $generator = $resolver->resolve($request, new ArgumentMetadata('', UserEntity::class, false, false, ''));
            if ($generator instanceof \Traversable) {
                iterator_to_array($generator);
            }
        } catch (\Exception $e) {
            $exception = $e;
        }

        if ($pass) {
            static::assertNull($exception, 'Exception: ' . ($exception !== null ? print_r($exception->getMessage(), true) : 'No Exception'));
        } else {
            if (!Feature::isActive('v6.8.0.0')) {
                static::assertInstanceOf(\RuntimeException::class, $exception, 'Exception: ' . ($exception !== null ? print_r($exception->getMessage(), true) : 'No Exception'));
            } else {
                static::assertInstanceOf(UserException::class, $exception);
            }
        }
    }

    /**
     * @return array<string, array{0: bool, 1: bool, 2: bool}>
     */
    public static function loginRequiredAnnotationData(): array
    {
        return [
            'Success Case' => [
                true, // loginRequired
                true, // context
                true, // pass
            ],
            'Missing annotation LoginRequired' => [
                false,
                true,
                false,
            ],
            'Missing sales-channel context' => [
                false,
                false,
                false,
            ],
        ];
    }

    private function loginUser(): string
    {
        $email = Uuid::randomHex() . '@example.com';
        $this->createUser($email);

        try {
            return $this->accountService->loginByCredentials($email, 'contena', $this->channelContext);
        } catch (BadCredentialsException) {
            // nth
        }

        return '';
    }

    private function getCurrencyId(string $isoCode): ?string
    {
        $currency = $this->currencyRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('isoCode', $isoCode)),
            Context::createDefaultContext()
        )->getEntities()->first();

        return $currency?->getId();
    }

    private function createTestChannel(): void
    {
        $usdCurrencyId = $this->getCurrencyId('USD');

        $this->createChannel([
            'id' => $this->ids->create('sales-channel'),
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
