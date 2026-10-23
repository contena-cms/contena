<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\Context;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartPersister;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItem\LineItemCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\Test\Generator;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class ChannelContextPersisterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private Connection $connection;

    private ChannelContextPersister $contextPersister;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $eventDispatcher = new EventDispatcher();
        $this->contextPersister = new ChannelContextPersister($this->connection, $eventDispatcher, static::getContainer()->get(CartPersister::class), new NativeClock());
    }

    public function testLoad(): void
    {
        $token = Random::getAlphanumericString(32);
        $expected = [
            'key' => 'value',
            'token' => $token,
            'expired' => false,
        ];

        $this->connection->insert('channel_api_context', [
            'token' => $token,
            'payload' => json_encode($expected),
            'channel_id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL),
        ]);

        static::assertSame($expected, $this->contextPersister->load($token, TestDefaults::CHANNEL));
    }

    public function testLoadPromotesUserIdFromColumnWhenMissingInPayload(): void
    {
        $token = Random::getAlphanumericString(32);
        $userId = $this->createUser();

        $this->connection->insert('channel_api_context', [
            'token' => $token,
            'payload' => json_encode([], \JSON_THROW_ON_ERROR),
            'channel_id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL),
            'user_id' => Uuid::fromHexToBytes($userId),
            'updated_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $result = $this->contextPersister->load($token, TestDefaults::CHANNEL);

        static::assertSame($userId, $result[ChannelContextService::CUSTOMER_ID]);
        static::assertSame($token, $result['token']);
        static::assertFalse($result['expired']);
    }

    public function testLoadKeepsPayloadUserIdWhenColumnDiffersOrIsNull(): void
    {
        $tokenWithNullColumn = Random::getAlphanumericString(32);
        $tokenWithDifferentColumn = Random::getAlphanumericString(32);
        $payloadUserId = $this->createUser();
        $columnUserId = $this->createUser();

        $this->connection->insert('channel_api_context', [
            'token' => $tokenWithNullColumn,
            'payload' => json_encode(['userId' => $payloadUserId], \JSON_THROW_ON_ERROR),
            'channel_id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL),
            'user_id' => null,
            'updated_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $this->connection->insert('channel_api_context', [
            'token' => $tokenWithDifferentColumn,
            'payload' => json_encode(['userId' => $payloadUserId], \JSON_THROW_ON_ERROR),
            'channel_id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL),
            'user_id' => Uuid::fromHexToBytes($columnUserId),
            'updated_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        static::assertSame(
            $payloadUserId,
            $this->contextPersister->load($tokenWithNullColumn, TestDefaults::CHANNEL)[ChannelContextService::CUSTOMER_ID]
        );
        static::assertSame(
            $payloadUserId,
            $this->contextPersister->load($tokenWithDifferentColumn, TestDefaults::CHANNEL)[ChannelContextService::CUSTOMER_ID]
        );
    }

    public function testLoadByUserId(): void
    {
        $token = Uuid::randomHex();
        $userId = $this->createUser();
        $this->contextPersister->save($token, [], TestDefaults::CHANNEL, $userId);

        static::assertNotCount(0, $result = $this->contextPersister->load($token, TestDefaults::CHANNEL, $userId));
        static::assertSame($token, $result['token']);
    }

    public function testLoadNotExisting(): void
    {
        $token = Random::getAlphanumericString(32);

        static::assertSame([], $this->contextPersister->load($token, TestDefaults::CHANNEL));
    }

    public function testLoadUserNotExisting(): void
    {
        $userId = Uuid::randomHex();
        $token = Random::getAlphanumericString(32);

        static::assertSame([], $this->contextPersister->load($token, TestDefaults::CHANNEL, $userId));
    }

    public function testLoadKeepsPayloadWhenTokenExpiresAndUserIdIsProvided(): void
    {
        $token = Random::getAlphanumericString(32);
        $expected = $payload = [
            'key' => 'value',
            'anotherKey' => 'anotherValue',
            'expired' => false,
            'token' => $token,
        ];

        $this->contextPersister->save($token, $payload, TestDefaults::CHANNEL);

        $this->makeTokenAge($token, 2);

        // Load with userId should keep the payload and just mark it as expired
        $expected['expired'] = true;
        static::assertSame($expected, $this->contextPersister->load($token, TestDefaults::CHANNEL, Uuid::randomHex()));
    }

    public function testLoadWithdrawPayloadWhenTokenExpiresAndUserIdIsNotProvided(): void
    {
        $token = Random::getAlphanumericString(32);
        $payload = [
            'key' => 'value',
            'anotherKey' => 'anotherValue',
            'expired' => false,
            'token' => $token,
        ];

        $this->contextPersister->save($token, $payload, TestDefaults::CHANNEL);

        $this->makeTokenAge($token, 2);

        // Everything except 'expired' and 'token' should be removed when loading without userId
        static::assertSame(['expired' => true, 'token' => $token], $this->contextPersister->load($token, TestDefaults::CHANNEL));
    }

    public function testSaveWithoutExistingContext(): void
    {
        $token = Random::getAlphanumericString(32);
        $expected = [
            'key' => 'value',
            'expired' => false,
            'token' => $token,
        ];

        $this->contextPersister->save($token, $expected, TestDefaults::CHANNEL);

        static::assertSame($expected, $this->contextPersister->load($token, TestDefaults::CHANNEL));
    }

    public function testSaveNewUserContextWithoutExistingUser(): void
    {
        $token = Random::getAlphanumericString(32);
        $payload = [
            'key' => 'value',
            'token' => $token,
            'expired' => false,
        ];

        $userId = $this->createUser();

        $this->contextPersister->save($token, $payload, TestDefaults::CHANNEL, $userId);

        $result = $this->contextPersister->load($token, TestDefaults::CHANNEL, $userId);

        static::assertNotCount(0, $result);

        $expected = $payload;
        $expected[ChannelContextService::CUSTOMER_ID] = $userId;

        static::assertEquals($expected, $result);
        static::assertSame($token, $result['token']);
    }

    public function testSaveMergesWithExisting(): void
    {
        $token = Random::getAlphanumericString(32);

        $this->connection->insert('channel_api_context', [
            'token' => $token,
            'payload' => json_encode([
                'first' => 'test',
                'second' => 'second test',
            ]),
            'channel_id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL),
        ]);

        $this->contextPersister->save(
            $token,
            [
                'second' => 'overwritten',
                'third' => 'third test',
            ],
            TestDefaults::CHANNEL
        );

        $expected = [
            'expired' => false,
            'first' => 'test',
            'second' => 'overwritten',
            'third' => 'third test',
            'token' => $token,
        ];

        $actual = $this->contextPersister->load($token, TestDefaults::CHANNEL);
        ksort($actual);

        static::assertSame($expected, $actual);
    }

    public function testSaveUserContextMergesWithExisting(): void
    {
        $token = Random::getAlphanumericString(32);

        $userId = $this->createUser();

        $this->connection->insert('channel_api_context', [
            'token' => $token,
            'payload' => json_encode([
                'first' => 'test',
                'second' => 'second test',
            ]),
            'channel_id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL),
            'user_id' => Uuid::fromHexToBytes($userId),
        ]);

        $this->contextPersister->save($token, [
            'second' => 'overwritten',
            'third' => 'third test',
        ], TestDefaults::CHANNEL, $userId);

        $expected = [
            ChannelContextService::CUSTOMER_ID => $userId,
            'expired' => false,
            'first' => 'test',
            'second' => 'overwritten',
            'third' => 'third test',
            'token' => $token,
        ];
        $actual = $this->contextPersister->load($token, TestDefaults::CHANNEL, $userId);
        ksort($actual);

        static::assertSame($expected, $actual);
    }

    public function testSaveReplacesUserContextToken(): void
    {
        $userId = $this->createUser();
        $oldToken = Random::getAlphanumericString(32);
        $newToken = Random::getAlphanumericString(32);

        $this->contextPersister->save($oldToken, ['first' => 'value'], TestDefaults::CHANNEL, $userId);
        $this->contextPersister->save($newToken, ['second' => 'value'], TestDefaults::CHANNEL, $userId);

        static::assertFalse($this->contextExists($oldToken));
        $context = $this->contextPersister->load($newToken, TestDefaults::CHANNEL, $userId);
        ksort($context);

        static::assertSame(
            [
                ChannelContextService::CUSTOMER_ID => $userId,
                'expired' => false,
                'first' => 'value',
                'second' => 'value',
                'token' => $newToken,
            ],
            $context
        );
    }

    public function testSaveReplacesConflictingTokenAndUserContext(): void
    {
        $userId = $this->createUser();
        $token = Random::getAlphanumericString(32);
        $userToken = Random::getAlphanumericString(32);

        $this->contextPersister->save($token, ['guest' => 'value'], TestDefaults::CHANNEL);
        $this->contextPersister->save($userToken, ['user' => 'value'], TestDefaults::CHANNEL, $userId);
        $this->contextPersister->save($token, ['new' => 'value'], TestDefaults::CHANNEL, $userId);

        static::assertFalse($this->contextExists($userToken));
        $context = $this->contextPersister->load($token, TestDefaults::CHANNEL, $userId);
        ksort($context);

        static::assertSame(
            [
                'user' => 'value',
                ChannelContextService::CUSTOMER_ID => $userId,
                'expired' => false,
                'new' => 'value',
                'token' => $token,
            ],
            $context
        );
    }

    public function testLoadSameUserOnDifferentChannel(): void
    {
        $userId = $this->createUser();

        $channel1 = $this->createChannel([
            'id' => Uuid::randomHex(),
            'domains' => [
                [
                    'url' => 'http://test.en',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);

        $channel2 = $this->createChannel([
            'id' => Uuid::randomHex(),
            'domains' => [
                [
                    'url' => 'http://test.de',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);

        $token1 = Uuid::randomHex();
        $token2 = Uuid::randomHex();

        $this->contextPersister->save($token1, [], $channel1['id'], $userId);
        $this->contextPersister->save($token2, [], $channel2['id'], $userId);

        // Without saved context sales channel
        static::assertCount(0, $this->contextPersister->load($token1, TestDefaults::CHANNEL, $userId));
        static::assertCount(0, $this->contextPersister->load($token2, TestDefaults::CHANNEL, $userId));

        $contextPayload1 = $this->contextPersister->load(Uuid::randomHex(), $channel1['id'], $userId);
        static::assertNotCount(0, $contextPayload1);
        static::assertSame($token1, $contextPayload1['token']);

        $contextPayload2 = $this->contextPersister->load(Uuid::randomHex(), $channel2['id'], $userId);

        static::assertNotCount(0, $contextPayload2);
        static::assertSame($token2, $contextPayload2['token']);
    }

    public function testReplaceWithoutExistingContext(): void
    {
        $token = Random::getAlphanumericString(32);

        $context = Generator::generateChannelContext(overrides: ['user' => null]);
        $newToken = $this->contextPersister->replace($token, $context);

        static::assertTrue($this->contextExists($newToken));
        static::assertFalse($this->contextExists($token));
    }

    public function testSaveReplaceWithExistingContext(): void
    {
        $token = Random::getAlphanumericString(32);

        $this->connection->insert('channel_api_context', [
            'token' => $token,
            'payload' => json_encode([
                'first' => 'test',
                'second' => 'second test',
            ]),
            'channel_id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL),
        ]);

        $context = Generator::generateChannelContext(overrides: ['user' => null]);
        $newToken = $this->contextPersister->replace($token, $context);

        static::assertTrue($this->contextExists($newToken));
        static::assertFalse($this->contextExists($token));
    }

    public function testReplaceUpdatesCartTokenToo(): void
    {
        $token = Random::getAlphanumericString(32);

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create($token, TestDefaults::CHANNEL);

        $cart = new Cart($token);
        $cart->addLineItems(new LineItemCollection([new LineItem('test', 'test', Uuid::randomHex(), 10)]));
        static::getContainer()->get(CartPersister::class)->save($cart, $context);

        static::assertTrue($this->cartExists($token));

        $newToken = $this->contextPersister->replace($token, $context);

        static::assertTrue($this->cartExists($newToken));
        static::assertFalse($this->cartExists($token));
    }

    public function testUserIdColumnIsBeingUsed(): void
    {
        $userId = $this->createUser();
        $token = Random::getAlphanumericString(32);

        $this->connection->insert('channel_api_context', [
            'token' => $token,
            'payload' => json_encode(['foo' => 'bar']),
            'user_id' => Uuid::fromHexToBytes($userId),
            'channel_id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL),
        ]);

        $this->contextPersister->revokeAllUserTokens($userId);

        static::assertNull($this->connection->fetchOne('SELECT user_id FROM channel_api_context'));
    }

    public static function tokenExpiringDataProvider(): \Generator
    {
        yield [0, 'P2D', false];
        yield [1, 'P2D', false];
        yield [3, 'P2D', true];
        yield [0, 'P1D', false];
        yield [2, 'P1D', true];
    }

    #[DataProvider('tokenExpiringDataProvider')]
    public function testTokenExpiring(int $tokenAgeInDays, string $lifeTimeInterval, bool $expectedExpired): void
    {
        $persister = new ChannelContextPersister(
            $this->connection,
            static::createStub(EventDispatcher::class),
            static::getContainer()->get(CartPersister::class),
            new NativeClock(),
            $lifeTimeInterval,
        );
        $token = Uuid::randomHex();

        $userId = $this->createUser();
        $persister->save($token, [], TestDefaults::CHANNEL, $userId);

        $this->makeTokenAge($token, $tokenAgeInDays);

        $result = $persister->load($token, TestDefaults::CHANNEL, $userId);

        static::assertSame($result['expired'], $expectedExpired);
        static::assertSame($userId, $result[ChannelContextService::CUSTOMER_ID]);
    }

    #[DataProvider('revokeTokensTestDataProvider')]
    public function testRevokeTokens(string $token, ?string $preserveToken): void
    {
        $userId = $this->createUser();
        $this->contextPersister->save($token, [], TestDefaults::CHANNEL, $userId);

        // check token is valid here
        static::assertNotCount(0, $result = $this->contextPersister->load($token, TestDefaults::CHANNEL, $userId));
        static::assertSame($token, $result['token']);

        if ($preserveToken) {
            $this->contextPersister->revokeAllUserTokens($userId, $preserveToken);
        } else {
            $this->contextPersister->revokeAllUserTokens($userId);
        }

        if ($preserveToken) {
            static::assertNotNull($this->connection->fetchOne('SELECT user_id FROM channel_api_context'));
        } else {
            static::assertNull($this->connection->fetchOne('SELECT user_id FROM channel_api_context'));
        }
    }

    public static function revokeTokensTestDataProvider(): \Generator
    {
        yield [Uuid::randomHex(), ''];
        yield [$token = Uuid::randomHex(), $token];
    }

    private function cartExists(string $token): bool
    {
        $result = (int) $this->connection->executeQuery(
            'SELECT COUNT(*) FROM cart WHERE `token` = :token',
            [
                'token' => $token,
            ]
        )->fetchOne();

        return $result > 0;
    }

    private function contextExists(string $token): bool
    {
        $result = (int) $this->connection->executeQuery(
            'SELECT COUNT(*) FROM channel_api_context WHERE `token` = :token',
            [
                'token' => $token,
            ]
        )->fetchOne();

        return $result > 0;
    }

    /**
     * Changes the age of a token by updating the updated_at field in the database.
     */
    private function makeTokenAge(string $token, int $tokenAgeInDays): void
    {
        if ($tokenAgeInDays !== 0) {
            $this->connection->executeStatement(
                'UPDATE channel_api_context
                SET updated_at = DATE_ADD(updated_at, INTERVAL :intervalInDays DAY)
                WHERE token = :token',
                ['intervalInDays' => -$tokenAgeInDays, 'token' => $token]
            );
        }
    }
}
