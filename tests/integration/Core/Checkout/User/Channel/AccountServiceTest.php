<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Exception\PasswordPoliciesUpdatedException;
use Contena\Core\System\User\Channel\AccountService;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\ChannelFunctionalTestBehaviour;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class AccountServiceTest extends TestCase
{
    use ChannelFunctionalTestBehaviour;

    private AccountService $accountService;

    protected function setUp(): void
    {
        $this->accountService = static::getContainer()->get(AccountService::class);
    }

    protected function tearDown(): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->delete('core.systemWideLoginRegistration.isUserBoundToChannel');
    }

    public function testLoginById(): void
    {
        $channelContext = $this->createChannelContext();
        $userId = $this->createUserOfChannel($channelContext->getChannelId(), 'foo@bar.com');
        $token = $this->accountService->loginById($userId, $channelContext);

        $user = $this->getUserFromToken($token, $channelContext->getChannelId());

        static::assertSame('foo@bar.com', $user->getEmail());
        static::assertSame($userId, $user->getId());
    }

    public function testLoginByCredentials(): void
    {
        $channelContext = $this->createChannelContext();
        $userId = $this->createUserOfChannel($channelContext->getChannelId(), 'foo@bar.com');
        $token = $this->accountService->loginByCredentials('foo@bar.com', 'contena', $channelContext);

        $user = $this->getUserFromToken($token, $channelContext->getChannelId());

        static::assertSame('foo@bar.com', $user->getEmail());
        static::assertSame($userId, $user->getId());
    }

    public function testGetUserByLogin(): void
    {
        $email = 'johndoe@example.com';

        $context = $this->createChannelContext([
            'domains' => [
                [
                    'url' => 'https://test.de',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);
        $this->createUserOfChannel($context->getChannelId(), $email);

        $user = $this->accountService->getUserByLogin($email, 'contena', $context);
        static::assertSame($email, $user->getEmail());
        static::assertSame($context->getChannelId(), $user->getChannelId());
    }

    public function testGetUserByLoginWithInvalidPassword(): void
    {
        $email = 'johndoe@example.com';

        $context = $this->createChannelContext([
            'domains' => [
                [
                    'url' => 'https://test.de',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);
        $this->createUserOfChannel($context->getChannelId(), $email);

        $this->expectExceptionObject(UserException::badCredentials());
        $this->accountService->getUserByLogin($email, 'invalid-password', $context);
    }

    public function testGetUserByLoginWhenUsersHaveSameEmailReturnsTheLatestCreatedUser(): void
    {
        $idUser1 = Uuid::randomHex();
        $idUser2 = Uuid::randomHex();
        $email = 'johndoe@example.com';
        $context = $this->createChannelContext([
            'domains' => [
                [
                    'url' => 'https://test.de',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);

        $this->createUserOfChannel($context->getChannelId(), $email, true, true, $idUser1, '2022-10-21 10:00:00');
        $this->cloneUserWithDuplicateEmail($idUser1, $idUser2, '2022-10-22 10:00:00');

        $user = $this->accountService->getUserByLogin($email, 'contena', $context);
        static::assertSame($idUser2, $user->getId());
    }

    public function testGetUserByLoginWhenUsersInDifferentChannelsHaveSameEmail(): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.systemWideLoginRegistration.isUserBoundToChannel', true);

        $email = 'johndoe@example.com';

        $context1 = $this->createChannelContext([
            'domains' => [
                [
                    'url' => 'https://test.de',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);
        $this->createUserOfChannel($context1->getChannelId(), $email);

        $context2 = $this->createChannelContext([
            'domains' => [
                [
                    'url' => 'http://test.en',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);

        $this->createUserOfChannel($context2->getChannelId(), $email);

        $user1 = $this->accountService->getUserByLogin($email, 'contena', $context1);

        static::assertSame($context1->getChannelId(), $user1->getChannelId());

        $user2 = $this->accountService->getUserByLogin($email, 'contena', $context2);
        static::assertSame($context2->getChannelId(), $user2->getChannelId());
    }

    public function testUserFailsToLoginByMailWithInactiveAccount(): void
    {
        $email = 'johndoe@example.com';

        $context = $this->createChannelContext([
            'domains' => [
                [
                    'url' => 'https://test.de',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);
        $this->createUserOfChannel($context->getChannelId(), $email, true, false);

        $this->expectExceptionObject(UserException::badCredentials());
        $this->accountService->getUserByLogin($email, 'contena', $context);
    }

    public function testGetUserByLoginLegacyPasswordIsUpdatedToNewOne(): void
    {
        $idUser = Uuid::randomHex();
        $email = 'johndoe@example.com';

        $context = $this->createChannelContext([
            'domains' => [
                [
                    'url' => 'http://test.de',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);
        $this->createUserOfChannel($context->getChannelId(), $email, true, true, $idUser, '2022-10-21 10:00:00', Hasher::hash('contena', 'md5'), 'Md5');

        $user = $this->accountService->getUserByLogin($email, 'contena', $context);
        static::assertSame($email, $user->getEmail());
        static::assertSame($context->getChannelId(), $user->getChannelId());

        $user = $this
            ->getContainer()
            ->get('user.repository')
            ->search(new Criteria([$idUser]), $context->getContext())->getEntities()
            ->first();
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertNull($user->getLegacyPassword());
        static::assertNull($user->getLegacyEncoder());
        static::assertNotNull($user->getPassword());
    }

    public function testUserFailsToLoginByLegacyPasswordWithOutdatedPasswordPolicy(): void
    {
        $idUser = Uuid::randomHex();
        $email = 'johndoe@example.com';

        $context = $this->createChannelContext([
            'domains' => [
                [
                    'url' => 'http://test.de',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);
        $this->createUserOfChannel($context->getChannelId(), $email, true, true, $idUser, '2022-10-21 10:00:00', Hasher::hash('test', 'md5'), 'Md5');

        $this->expectExceptionObject(new PasswordPoliciesUpdatedException());
        $this->accountService->getUserByLogin($email, 'test', $context);
    }

    private function getUserFromToken(string $contextToken, string $channelId): UserEntity
    {
        $channelContextService = static::getContainer()->get(ChannelContextService::class);
        $context = $channelContextService->get(
            new ChannelContextServiceParameters($channelId, $contextToken)
        );

        $user = $context->getUser();
        static::assertNotNull($user);

        return $user;
    }

    private function createUserOfChannel(
        string $channelId,
        string $email,
        bool $boundToChannel = true,
        bool $active = true,
        ?string $userId = null,
        ?string $createdAt = null,
        ?string $password = TestDefaults::HASHED_PASSWORD,
        ?string $legacyEncoder = null
    ): string {
        $userId ??= Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'createdAt' => $createdAt,
            'number' => $userId,
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'userNumber' => $userId,
            'email' => $email,
            'password' => $legacyEncoder ? null : $password,
            'legacyEncoder' => $legacyEncoder,
            'legacyPassword' => $legacyEncoder ? $password : null,
            'boundChannelId' => $boundToChannel ? $channelId : null,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'channelId' => $channelId,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'active' => $active,
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

        static::getContainer()
            ->get('user.repository')
            ->upsert([$user], Context::createDefaultContext());

        return $userId;
    }

    private function cloneUserWithDuplicateEmail(string $sourceUserId, string $userId, string $createdAt): void
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
                'userId' => Uuid::fromHexToBytes($userId),
                'userNumber' => $userId,
                'createdAt' => $createdAt,
                'sourceUserId' => Uuid::fromHexToBytes($sourceUserId),
            ],
        );
    }
}
