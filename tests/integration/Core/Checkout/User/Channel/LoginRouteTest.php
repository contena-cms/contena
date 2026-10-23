<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartPersister;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Channel\LoginRoute;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('store-api')]
class LoginRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignChannelContext($this->browser);
        $this->userRepository = static::getContainer()->get('user.repository');
    }

    public function testInvalidCredentials(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => 'foo',
                    'password' => 'foo12345',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('Unauthorized', $response['errors'][0]['title']);
    }

    public function testEmptyRequest(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__CUSTOMER_AUTH_BAD_CREDENTIALS', $response['errors'][0]['code']);
    }

    public function testValidLogin(): void
    {
        $email = Uuid::randomHex() . '@exämple.com';
        $this->createUser($email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'contena',
                ]
            );

        $response = $this->browser->getResponse();

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);
    }

    public function testItNotUpdatesUserLanguageIdOnValidLogin(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $userId = $this->createUser($email, null, true, $this->getDeDeLanguageId());

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'contena',
                ],
            );

        static::assertSame(
            $this->getDeDeLanguageId(),
            $this->userRepository->search(
                new Criteria([$userId]),
                Context::createDefaultContext()
            )->getEntities()->first()?->getLanguageId()
        );
    }

    public function testValidLoginWithOneInactive(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $userId = $this->createUser($email);
        $this->cloneUserWithDuplicateEmail($userId, Uuid::randomHex(), false);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'contena',
                ]
            );

        $response = $this->browser->getResponse();

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);
    }

    public function testLoginWithInvalidBoundChannelId(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $channel = $this->createChannel([
            'id' => Uuid::randomHex(),
        ]);

        $channelContext = $this->createChannelContext(Uuid::randomHex(), [
            'id' => Uuid::randomHex(),
        ], null);

        $this->createUser($email, $channel['id']);

        $loginRoute = static::getContainer()->get(LoginRoute::class);

        $requestDataBag = new RequestDataBag(['email' => $email, 'password' => 'contena']);

        static::expectExceptionObject(UserException::badCredentials());
        $loginRoute->login($requestDataBag, $channelContext);
    }

    public function testLoginSuccessRestoreUserContext(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $userId = $this->createUser($email);
        $contextToken = Uuid::randomHex();

        $channelContext = $this->createChannelContext($contextToken, [], $userId);
        $this->createCart($contextToken, $channelContext);

        $loginRoute = static::getContainer()->get(LoginRoute::class);

        $request = new RequestDataBag(['email' => $email, 'password' => 'contena']);

        $response = $loginRoute->login($request, $channelContext);

        // Token is replace as there're no user token in the database
        static::assertNotSame($contextToken, $oldToken = $response->getToken());

        $channelContext = $this->createChannelContext('123456789', [], $userId);

        $response = $loginRoute->login($request, $channelContext);

        // Previous token is restored
        static::assertSame($oldToken, $response->getToken());

        // Previous Cart is restored
        $channelContext = $this->createChannelContext($oldToken, [], $userId);
        $oldCartExists = static::getContainer()->get(CartService::class)->getCart($oldToken, $channelContext);

        static::assertInstanceOf(Cart::class, $oldCartExists);
        static::assertSame($oldToken, $oldCartExists->getToken());
    }

    public function testUserHaveDifferentCartsOnEachChannel(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $userId = $this->createUser($email);

        $this->createChannel([
            'id' => $this->ids->get('sales-channel-1'),
            'domains' => [
                [
                    'url' => 'http://test.de',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);

        $this->createChannel([
            'id' => $this->ids->get('sales-channel-2'),
            'domains' => [
                [
                    'url' => 'http://test.en',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);

        $channelContext1 = $this->createChannelContext($this->ids->get('context-1'), [], $userId, $this->ids->get('sales-channel-1'));

        $channelContext2 = $this->createChannelContext($this->ids->get('context-2'), [], $userId, $this->ids->get('sales-channel-2'));

        $this->createCart($this->ids->get('context-1'), $channelContext1);

        $this->createCart($this->ids->get('context-2'), $channelContext2);

        $loginRoute = static::getContainer()->get(LoginRoute::class);

        $request = new RequestDataBag(['email' => $email, 'password' => 'contena']);

        $responseChannel1 = $loginRoute->login($request, $channelContext1);

        $responseChannel2 = $loginRoute->login($request, $channelContext2);

        static::assertNotSame($responseChannel1->getToken(), $responseChannel2->getToken());

        $cartService = static::getContainer()->get(CartService::class);

        $cartFromChannel1 = $cartService->getCart($responseChannel1->getToken(), $channelContext1, false);
        $cartFromChannel2 = $cartService->getCart($responseChannel2->getToken(), $channelContext2, false);

        static::assertNotSame($cartFromChannel1->getToken(), $cartFromChannel2->getToken());
    }

    private function createCart(string $contextToken, ChannelContext $context): void
    {
        $persister = static::getContainer()->get(CartPersister::class);

        $persister->save(new Cart($contextToken), $context);
    }

    /**
     * @param array<string, mixed> $channelData
     */
    private function createChannelContext(string $contextToken, array $channelData, ?string $userId, ?string $channelId = null): ChannelContext
    {
        if ($userId) {
            $channelData[ChannelContextService::CUSTOMER_ID] = $userId;
        }

        return static::getContainer()->get(ChannelContextFactory::class)->create(
            $contextToken,
            $channelId ?? TestDefaults::CHANNEL,
            $channelData
        );
    }

    private function createUser(?string $email = null, ?string $boundChannelId = null, bool $active = true, ?string $languageId = null): string
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schoöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'country' => ['name' => 'Germany'],
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => $userId,
            'boundChannelId' => $boundChannelId,
            'active' => $active,
        ];

        if ($languageId !== null) {
            $user['languageId'] = $languageId;
        }

        $this->userRepository->create([$user], Context::createDefaultContext());

        return $userId;
    }

    private function cloneUserWithDuplicateEmail(string $sourceUserId, string $userId, bool $active): void
    {
        $connection = static::getContainer()->get(Connection::class);
        /** @var list<array{Field: string, Extra: string}> $columns */
        $columns = $connection->fetchAllAssociative('SHOW COLUMNS FROM `user`');

        $insertColumns = [];
        $selectExpressions = [];

        foreach ($columns as $column) {
            if (str_contains($column['Extra'], 'auto_increment')) {
                continue;
            }

            $field = $column['Field'];
            $insertColumns[] = '`' . $field . '`';
            $selectExpressions[] = match ($field) {
                'id' => ':userId',
                'active' => ':active',
                'user_number' => ':userNumber',
                'created_at' => ':createdAt',
                'updated_at' => 'NULL',
                default => '`' . $field . '`',
            };
        }

        // This test covers login behavior with legacy duplicate user rows that normal writes now reject.
        $connection->executeStatement(
            'INSERT INTO `user` (' . implode(', ', $insertColumns) . ')
             SELECT ' . implode(', ', $selectExpressions) . '
             FROM `user`
             WHERE `id` = :sourceUserId',
            [
                'active' => (int) $active,
                'createdAt' => '2022-10-22 10:00:00',
                'userId' => Uuid::fromHexToBytes($userId),
                'userNumber' => $userId,
                'sourceUserId' => Uuid::fromHexToBytes($sourceUserId),
            ],
        );
    }
}
