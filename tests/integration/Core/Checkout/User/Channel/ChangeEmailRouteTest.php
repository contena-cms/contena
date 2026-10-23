<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryCollection;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Integration\Traits\UserTestTrait;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
class ChangeEmailRouteTest extends TestCase
{
    use UserTestTrait;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    private string $userId;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignChannelContext($this->browser);
        $this->userRepository = static::getContainer()->get('user.repository');

        $email = Uuid::randomHex() . '@example.com';
        $this->userId = $this->createUser('contena', $email);

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

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }

    protected function tearDown(): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->delete('core.systemWideLoginRegistration.isUserBoundToChannel');
    }

    public function testEmptyRequest(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-email',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('VIOLATION::CUSTOMER_PASSWORD_NOT_CORRECT', $response['errors'][0]['code']);
    }

    public function testChangeInvalidPassword(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-email',
                [
                    'password' => 'foooware',
                    'email' => 'test@fooware.de',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('VIOLATION::CUSTOMER_PASSWORD_NOT_CORRECT', $response['errors'][0]['code']);
    }

    public function testChange(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-email',
                [
                    'password' => 'contena',
                    'email' => 'test@fooware.de',
                    'emailConfirmation' => 'test@fooware.de',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayNotHasKey('errors', $response);
        static::assertTrue($response['success']);

        $this->browser
            ->request(
                'GET',
                '/store-api/account/user',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('test@fooware.de', $response['email']);
    }

    public function testChangeAndDeleteOldRecoveryEntities(): void
    {
        $recoveryData = [
            'userId' => $this->userId,
            'hash' => Random::getAlphanumericString(32),
        ];

        /** @var EntityRepository<UserRecoveryCollection> $userRecoveryRepository */
        $userRecoveryRepository = $this->getContainer()->get('user_recovery.repository');
        $userRecoveryRepository->create([$recoveryData], Context::createDefaultContext());

        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-email',
                [
                    'password' => 'contena',
                    'email' => 'test@fooware.de',
                    'emailConfirmation' => 'test@fooware.de',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayNotHasKey('errors', $response);
        static::assertTrue($response['success']);

        $this->browser
            ->request(
                'GET',
                '/store-api/account/user',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $criteria = (new Criteria())->addFilter(new EqualsFilter('userId', $this->userId));
        $ids = $userRecoveryRepository->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertSame('test@fooware.de', $response['email']);
        static::assertCount(0, $ids);
    }

    public function testChangeSuccessWithSameEmailOnDiffChannel(): void
    {
        static::getContainer()->get(SystemConfigService::class)->set('core.systemWideLoginRegistration.isUserBoundToChannel', true);
        $this->userRepository->update([
            [
                'id' => $this->userId,
                'boundChannelId' => $this->ids->get('sales-channel'),
            ],
        ], Context::createDefaultContext());

        $newEmail = 'test@fooware.de';

        $channelContext = $this->createChannelContext([
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost2',
                ],
            ],
        ]);

        $this->createUserOfChannel($channelContext->getChannelId(), $newEmail);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-email',
                [
                    'password' => 'contena',
                    'email' => $newEmail,
                    'emailConfirmation' => $newEmail,
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayNotHasKey('errors', $response);
        static::assertTrue($response['success']);

        $this->browser
            ->request(
                'GET',
                '/store-api/account/user',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame($newEmail, $response['email']);
    }

    public function testChangeFailWithSameEmailOnSameChannel(): void
    {
        $newEmail = 'test@fooware.de';

        $this->createUserOfChannel($this->ids->get('sales-channel'), $newEmail);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-email',
                [
                    'password' => 'contena',
                    'email' => $newEmail,
                    'emailConfirmation' => $newEmail,
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame(400, $this->browser->getResponse()->getStatusCode());
        static::assertNotCount(0, $response['errors']);
        static::assertSame('VIOLATION::CUSTOMER_EMAIL_NOT_UNIQUE', $response['errors'][0]['code']);

        $this->browser
            ->request(
                'GET',
                '/store-api/account/user',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotSame($newEmail, $response['email']);
    }

    public function testChangeSuccessWithNewsletterRecipient(): void
    {
        $this->browser
            ->request(
                'GET',
                '/store-api/account/user',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->browser
            ->request(
                'POST',
                '/store-api/newsletter/subscribe',
                [
                    'email' => $response['email'],
                    'option' => 'direct',
                    'frontendUrl' => 'http://localhost',
                ]
            );

        $count = (int) static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT COUNT(*) FROM newsletter_recipient WHERE status = "direct" AND email = ?', [$response['email']]);
        static::assertSame(1, $count);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-email',
                [
                    'password' => 'contena',
                    'email' => 'test@fooware.de',
                    'emailConfirmation' => 'test@fooware.de',
                ]
            );

        $count = (int) static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT COUNT(*) FROM newsletter_recipient WHERE status = "direct" AND email = ?', [$response['email']]);
        static::assertSame(0, $count);

        $email = static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT email FROM newsletter_recipient WHERE status = "direct" AND email = "test@fooware.de"');
        static::assertSame($email, 'test@fooware.de');
    }

    private function createUser(string $password, ?string $email = null): string
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
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,

            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $email,
            'password' => $password,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => $userId,
        ];

        $this->userRepository->create([$user], Context::createDefaultContext());

        return $userId;
    }

    private function createUserOfChannel(string $channelId, string $email): string
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'number' => $userId,
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'userNumber' => $userId,
            'email' => $email,
            'password' => 'contena',
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'channelId' => $channelId,
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

        $isUserBound = static::getContainer()->get(SystemConfigService::class)->get('core.systemWideLoginRegistration.isUserBoundToChannel');
        $user['boundChannelId'] = $isUserBound ? $channelId : null;

        static::getContainer()
            ->get('user.repository')
            ->upsert([$user], Context::createDefaultContext());

        return $userId;
    }
}
