<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Subscriber;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\Validation\Constraint\UserEmailUnique;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class UserEmailUniqueSubscriberTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    private Context $context;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->userRepository = static::getContainer()->get('user.repository');
        $this->context = Context::createDefaultContext();
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);
    }

    protected function tearDown(): void
    {
        $this->systemConfigService->delete('core.systemWideLoginRegistration.isUserBoundToChannel');

        parent::tearDown();
    }

    public function testRepositoryCreateRejectsDuplicateEmailWhenUsersAreNotBoundToChannel(): void
    {
        $this->setUserBoundToChannel(false);

        $email = Uuid::randomHex() . '@example.com';
        $this->createUser($email);

        $this->expectUserEmailViolation(fn () => $this->createUser($email));
    }

    public function testAdminApiCreateRejectsDuplicateEmailWhenUsersAreNotBoundToChannel(): void
    {
        $this->setUserBoundToChannel(false);

        $email = Uuid::randomHex() . '@example.com';
        $this->createUser($email);

        $this->getBrowser()->jsonRequest('POST', '/api/user', $this->createUserPayload($email));

        $response = $this->getBrowser()->getResponse();
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame(UserEmailUnique::CUSTOMER_EMAIL_NOT_UNIQUE, $content['errors'][0]['code']);
    }

    public function testSyncApiCreateRejectsDuplicateEmailWithSyncOperationPointer(): void
    {
        $this->setUserBoundToChannel(false);

        $email = Uuid::randomHex() . '@example.com';
        $this->createUser($email);

        $this->getBrowser()->jsonRequest('POST', '/api/_action/sync', [[
            'key' => 'write-user',
            'action' => 'upsert',
            'entity' => 'user',
            'payload' => [$this->createUserPayload($email)],
        ]]);

        $response = $this->getBrowser()->getResponse();
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame('/write-user/0/email', $content['errors'][0]['source']['pointer']);
    }

    public function testRepositoryCreateAllowsDuplicateEmailForDifferentBoundChannelsWhenUsersAreBoundToChannel(): void
    {
        $this->setUserBoundToChannel(true);

        $email = Uuid::randomHex() . '@example.com';
        $channel = $this->createChannel([
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost2',
                ],
            ],
        ]);

        $this->createUser($email, ['boundChannelId' => TestDefaults::CHANNEL]);
        $this->createUser($email, [
            'channelId' => $channel['id'],
            'boundChannelId' => $channel['id'],
        ]);

        static::addToAssertionCount(1);
    }

    public function testRepositoryCreateRejectsDuplicateEmailForSameBoundChannelWhenUsersAreBoundToChannel(): void
    {
        $this->setUserBoundToChannel(true);

        $email = Uuid::randomHex() . '@example.com';
        $this->createUser($email, ['boundChannelId' => TestDefaults::CHANNEL]);

        $this->expectUserEmailViolation(fn () => $this->createUser($email, ['boundChannelId' => TestDefaults::CHANNEL]));
    }

    public function testRepositoryCreateAllowsDuplicateGuestEmail(): void
    {
        $this->setUserBoundToChannel(false);

        $email = Uuid::randomHex() . '@example.com';
        $this->createUser($email);
        $this->createUser($email, ['guest' => true]);

        static::addToAssertionCount(1);
    }

    public function testRepositoryUpdateRejectsDuplicateEmail(): void
    {
        $this->setUserBoundToChannel(false);

        $email = Uuid::randomHex() . '@example.com';
        $this->createUser($email);
        $userId = $this->createUser(Uuid::randomHex() . '@example.com');

        $this->expectUserEmailViolation(fn () => $this->userRepository->update([[
            'id' => $userId,
            'email' => $email,
        ]], $this->context));
    }

    public function testRepositoryCreateRejectsDuplicateEmailInSameWriteBatch(): void
    {
        $this->setUserBoundToChannel(false);

        $email = Uuid::randomHex() . '@example.com';

        $this->expectUserEmailViolation(fn () => $this->userRepository->create([
            $this->createUserPayload($email),
            $this->createUserPayload($email),
        ], $this->context));
    }

    public function testRepositoryUpdateAllowsEmailSwapInSameWriteBatch(): void
    {
        $this->setUserBoundToChannel(false);

        $firstEmail = Uuid::randomHex() . '@example.com';
        $secondEmail = Uuid::randomHex() . '@example.com';
        $firstUserId = $this->createUser($firstEmail);
        $secondUserId = $this->createUser($secondEmail);

        $this->userRepository->update([
            [
                'id' => $firstUserId,
                'email' => $secondEmail,
            ],
            [
                'id' => $secondUserId,
                'email' => $firstEmail,
            ],
        ], $this->context);

        static::addToAssertionCount(1);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createUser(string $email, array $overrides = []): string
    {
        $user = $this->createUserPayload($email, $overrides);

        $this->userRepository->create([$user], $this->context);

        return $user['id'];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function createUserPayload(string $email, array $overrides = []): array
    {
        $userId = $overrides['id'] ?? Uuid::randomHex();
        $addressId = $overrides['defaultBillingAddressId'] ?? Uuid::randomHex();

        return array_replace_recursive([
            'id' => $userId,
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Ebbinghoff 10',
                'city' => 'Schöppingen',
                'zipcode' => '48624',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'guest' => false,
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => Uuid::randomHex(),
        ], $overrides);
    }

    private function expectUserEmailViolation(\Closure $callback): void
    {
        try {
            $callback();
            static::fail('Expected a user email uniqueness violation.');
        } catch (WriteException $exception) {
            $writeException = $exception->getExceptions()[0] ?? null;

            static::assertInstanceOf(WriteConstraintViolationException::class, $writeException);
            static::assertSame(UserEmailUnique::CUSTOMER_EMAIL_NOT_UNIQUE, $writeException->getViolations()->get(0)->getCode());
        }
    }

    private function setUserBoundToChannel(bool $value): void
    {
        $this->systemConfigService->set('core.systemWideLoginRegistration.isUserBoundToChannel', $value);
    }
}
