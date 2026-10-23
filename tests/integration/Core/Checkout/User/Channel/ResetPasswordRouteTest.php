<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryCollection;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('store-api')]
class ResetPasswordRouteTest extends TestCase
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

    public function testWithInvalidHash(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/recovery-password-confirm',
                [
                    'hash' => 'lol@lol.de',
                    'newPassword' => 'password123456',
                    'newPasswordConfirm' => 'password123456',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__CUSTOMER_RECOVERY_HASH_EXPIRED', $response['errors'][0]['code']);
    }

    public function testSuccessReset(): void
    {
        $userId = $this->createUser('contena1234', 'foo-test@test.de');

        $this->browser
            ->request(
                'POST',
                '/store-api/account/recovery-password',
                [
                    'email' => 'foo-test@test.de',
                    'frontendUrl' => 'http://localhost',
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('userId', $userId));

        /** @var EntityRepository<UserRecoveryCollection> $repo */
        $repo = static::getContainer()->get('user_recovery.repository');

        $recovery = $repo->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(UserRecoveryEntity::class, $recovery);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/recovery-password-confirm',
                [
                    'hash' => $recovery->getHash(),
                    'newPassword' => 'password123456',
                    'newPasswordConfirm' => 'password123456',
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => 'foo-test@test.de',
                    'password' => 'password123456',
                ]
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);
    }

    public function testSuccessResetWithLegacyPassword(): void
    {
        $userId = $this->createUser('contena1234', 'foo-test@test.de', true);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/recovery-password',
                [
                    'email' => 'foo-test@test.de',
                    'frontendUrl' => 'http://localhost',
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('userId', $userId));

        /** @var EntityRepository<UserRecoveryCollection> $repo */
        $repo = static::getContainer()->get('user_recovery.repository');

        $recovery = $repo->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(UserRecoveryEntity::class, $recovery);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/recovery-password-confirm',
                [
                    'hash' => $recovery->getHash(),
                    'newPassword' => 'password123456',
                    'newPasswordConfirm' => 'password123456',
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => 'foo-test@test.de',
                    'password' => 'password123456',
                ]
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        $criteria = new Criteria([$userId]);

        /** @var UserEntity $user */
        $user = static::getContainer()->get('user.repository')->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertNull($user->getLegacyEncoder());
        static::assertNull($user->getLegacyPassword());
    }

    public function testSuccessResetConfirmsUnconfirmedDoubleOptInUser(): void
    {
        $userId = $this->createUser('contena1234', 'double-opt-in@test.de', false, true);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/recovery-password',
                [
                    'email' => 'double-opt-in@test.de',
                    'frontendUrl' => 'http://localhost',
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('userId', $userId));

        /** @var EntityRepository<UserRecoveryCollection> $repo */
        $repo = static::getContainer()->get('user_recovery.repository');

        $recovery = $repo->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(UserRecoveryEntity::class, $recovery);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/recovery-password-confirm',
                [
                    'hash' => $recovery->getHash(),
                    'newPassword' => 'password123456',
                    'newPasswordConfirm' => 'password123456',
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());

        $user = $this->fetchUser($userId);
        static::assertNotNull($user->getDoubleOptInConfirmDate());

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => 'double-opt-in@test.de',
                    'password' => 'password123456',
                ]
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);
    }

    private function createUser(string $password, ?string $email = null, bool $addLegacyPassword = false, bool $doubleOptInRegistration = false): string
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
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $email,
            'password' => $password,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'userNumber' => '12345',
            'doubleOptInRegistration' => $doubleOptInRegistration,
        ];

        if ($addLegacyPassword) {
            $user['legacyPassword'] = Hasher::hash('test', 'md5');
            $user['legacyEncoder'] = 'Md5';
        }

        $this->userRepository->create([
            $user,
        ], Context::createDefaultContext());

        return $userId;
    }

    private function fetchUser(string $userId): UserEntity
    {
        $criteria = new Criteria([$userId]);

        $user = $this->userRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(UserEntity::class, $user);

        return $user;
    }
}
