<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\RequestStackTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\ChannelRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * @internal
 */
class UserTokenSubscriberTest extends TestCase
{
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use RequestStackTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->userRepository = static::getContainer()->get('user.repository');
    }

    public function testUserTokenSubscriber(): void
    {
        $userId = $this->createUser();

        $this->connection->insert('channel_api_context', [
            'user_id' => Uuid::fromHexToBytes($userId),
            'token' => 'test',
            'channel_id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL),
            'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'payload' => '{"userId": "1234"}',
        ]);

        $this->userRepository->update([
            [
                'id' => $userId,
                'password' => 'fooo12345',
            ],
        ], Context::createDefaultContext());

        static::assertSame(
            [
                'userId' => null,
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ],
            \json_decode((string) $this->connection->fetchOne('SELECT payload FROM channel_api_context WHERE token = "test"'), true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    public function testUserTokenSubscriberFrontendShouldStillBeLoggedIn(): void
    {
        $userId = $this->createUser();

        $request = Request::create('/');
        // a frontend request owns the session
        $request->attributes->set(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST, true);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $context = static::createStub(ChannelContext::class);
        $context->method('getToken')->willReturn('test');
        $context->method('getUserId')->willReturn($userId);
        $context->method('getChannelId')->willReturn(TestDefaults::CHANNEL);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);

        static::getContainer()->get('request_stack')->push($request);

        $newToken = null;

        $context->method('assign')->willReturnCallback(static function ($array) use ($context, &$newToken) {
            $newToken = $array['token'];

            return $context;
        });

        $this->connection->insert('channel_api_context', [
            'user_id' => Uuid::fromHexToBytes($userId),
            'token' => 'test',
            'channel_id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL),
            'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'payload' => '{"userId": "1234"}',
        ]);

        $this->userRepository->update([
            [
                'id' => $userId,
                'password' => 'fooo12345',
            ],
        ], Context::createDefaultContext());

        static::assertNotNull($newToken);

        static::assertSame(
            [
                'userId' => '1234',
            ],
            \json_decode((string) $this->connection->fetchOne('SELECT payload FROM channel_api_context WHERE token = ?', [$newToken]), true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    public function testDeleteUser(): void
    {
        $userId = $this->createUser();

        $this->connection->insert('channel_api_context', [
            'user_id' => Uuid::fromHexToBytes($userId),
            'token' => 'test',
            'channel_id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL),
            'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'payload' => '{"userId": "1234"}',
        ]);

        $this->userRepository->delete([
            [
                'id' => $userId,
            ],
        ], Context::createDefaultContext());

        static::assertCount(0, $this->connection->fetchAllAssociative('SELECT * FROM channel_api_context WHERE token = ?', ['test']));
    }

    private function createUser(): string
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'userNumber' => '1337',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'channelId' => TestDefaults::CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'userId' => $userId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->userRepository->upsert([$user], Context::createDefaultContext());

        return $userId;
    }
}
