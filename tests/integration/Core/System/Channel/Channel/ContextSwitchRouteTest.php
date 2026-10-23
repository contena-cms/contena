<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressCollection;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Group('store-api')]
class ContextSwitchRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    /**
     * @var EntityRepository<UserAddressCollection>
     */
    private EntityRepository $userAddressRepository;

    protected function setUp(): void
    {
        $kernel = KernelLifecycleManager::getKernel();
        $this->userRepository = $kernel->getContainer()->get('user.repository');
        $this->userAddressRepository = $kernel->getContainer()->get('user_address.repository');
    }

    public function testUpdateContextWithNonExistingParameters(): void
    {
        $testId = Uuid::randomHex();

        /*
         * Shipping method
         */
        $this->getChannelBrowser()->request('PATCH', '/store-api/context', ['shippingMethodId' => $testId]);
        $content = json_decode($this->getChannelBrowser()->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            \sprintf('The "shipping_method" entity with id "%s" does not exist.', $testId),
            $content['errors'][0]['detail'] ?? null
        );

        /*
         * Payment method
         */
        $this->getChannelBrowser()->request('PATCH', '/store-api/context', ['paymentMethodId' => $testId]);
        $content = json_decode($this->getChannelBrowser()->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            \sprintf('The "payment_method" entity with id "%s" does not exist.', $testId),
            $content['errors'][0]['detail'] ?? null
        );
    }

    public function testUpdateContextWithNonLoggedInUser(): void
    {
        $testId = Uuid::randomHex();

        /*
         * Billing address
         */
        $this->getChannelBrowser()->request('PATCH', '/store-api/context', ['billingAddressId' => $testId]);
        $content = json_decode($this->getChannelBrowser()->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_FORBIDDEN, $this->getChannelBrowser()->getResponse()->getStatusCode());

        static::assertSame(
            'User is not logged in.',
            $content['errors'][0]['detail'] ?? null
        );

        /*
         * Shipping address
         */
        $this->getChannelBrowser()->request('PATCH', '/store-api/context', ['shippingAddressId' => $testId]);
        $content = json_decode($this->getChannelBrowser()->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_FORBIDDEN, $this->getChannelBrowser()->getResponse()->getStatusCode());

        static::assertSame(
            'User is not logged in.',
            $content['errors'][0]['detail'] ?? null
        );
    }

    public function testUpdateContextWithNonLoggedInUserAndEmptyAddressIds(): void
    {
        $this->getChannelBrowser()->request('PATCH', '/store-api/context', [
            'billingAddressId' => '',
            'shippingAddressId' => '',
        ]);

        static::assertSame(Response::HTTP_OK, $this->getChannelBrowser()->getResponse()->getStatusCode());
    }

    public function testInvalidParameters(): void
    {
        $this->getChannelBrowser()->request(
            'PATCH',
            '/store-api/context',
            [
                'currencyId' => [Uuid::randomHex(), 'foo'],
                'billingAddressId' => [Uuid::randomHex(), Uuid::randomHex()],
                'shippingAddressId' => [Uuid::randomHex(), Uuid::randomHex()],
                'paymentMethodId' => [Uuid::randomHex(), Uuid::randomHex()],
                'shippingMethodId' => [Uuid::randomHex(), Uuid::randomHex()],
                'countryId' => [Uuid::randomHex(), Uuid::randomHex()],
                'countryStateId' => [Uuid::randomHex(), Uuid::randomHex()],
                'languageId' => [Uuid::randomHex(), Uuid::randomHex()],
            ]
        );

        static::assertSame(400, $this->getChannelBrowser()->getResponse()->getStatusCode());

        $errors = json_decode($this->getChannelBrowser()->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR) ?: [];

        static::assertCount(8, $errors['errors']);

        $mapped = [];
        foreach ($errors['errors'] as $error) {
            $mapped[$error['source']['pointer']] = $error['detail'];
        }

        static::assertArrayHasKey('/currencyId', $mapped);
        static::assertArrayHasKey('/billingAddressId', $mapped);
        static::assertArrayHasKey('/shippingAddressId', $mapped);
        static::assertArrayHasKey('/paymentMethodId', $mapped);
        static::assertArrayHasKey('/shippingMethodId', $mapped);
        static::assertArrayHasKey('/countryId', $mapped);
        static::assertArrayHasKey('/countryStateId', $mapped);
        static::assertArrayHasKey('/languageId', $mapped);

        static::assertSame('This value should be of type string.', $mapped['/currencyId']);
        static::assertSame('This value should be of type string.', $mapped['/billingAddressId']);
        static::assertSame('This value should be of type string.', $mapped['/shippingAddressId']);
        static::assertSame('This value should be of type string.', $mapped['/paymentMethodId']);
        static::assertSame('This value should be of type string.', $mapped['/shippingMethodId']);
        static::assertSame('This value should be of type string.', $mapped['/countryId']);
        static::assertSame('This value should be of type string.', $mapped['/countryStateId']);
        static::assertSame('This value should be of type string.', $mapped['/languageId']);
    }

    public function testUpdateContextWithLoggedInUserAndNonExistingAddresses(): void
    {
        $testId = Uuid::randomHex();

        $this->createUserAndLogin();

        /*
         * Billing address
         */
        $this->getChannelBrowser()->request('PATCH', '/store-api/context', ['billingAddressId' => $testId]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getChannelBrowser()->getResponse()->getStatusCode());
        $content = json_decode($this->getChannelBrowser()->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            \sprintf('The "user_address" entity with id "%s" does not exist.', $testId),
            $content['errors'][0]['detail'] ?? null
        );

        /*
         * Shipping address
         */
        $this->getChannelBrowser()->request('PATCH', '/store-api/context', ['shippingAddressId' => $testId]);
        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getChannelBrowser()->getResponse()->getStatusCode());
        $content = json_decode($this->getChannelBrowser()->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            \sprintf('The "user_address" entity with id "%s" does not exist.', $testId),
            $content['errors'][0]['detail'] ?? null
        );
    }

    public function testUpdateContextWithLoggedInUser(): void
    {
        $userId = $this->createUserAndLogin();
        $billingId = $this->createUserAddress($userId);
        $shippingId = $this->createUserAddress($userId);

        /*
         * Billing address
         */
        $this->getChannelBrowser()
            ->request('PATCH', '/store-api/context', ['billingAddressId' => $billingId]);
        static::assertSame(Response::HTTP_OK, $this->getChannelBrowser()->getResponse()->getStatusCode());

        /*
         * Shipping address
         */
        $this->getChannelBrowser()
            ->request('PATCH', '/store-api/context', ['shippingAddressId' => $shippingId]);
        static::assertSame(Response::HTTP_OK, $this->getChannelBrowser()->getResponse()->getStatusCode());
    }

    public function testSwitchToNotExistingLanguage(): void
    {
        $id = Uuid::randomHex();

        $this->getChannelBrowser()
            ->request('PATCH', '/store-api/context', ['languageId' => $id]);

        $response = $this->getChannelBrowser()->getResponse();

        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), print_r($content, true));

        static::assertSame(
            \sprintf('The "language" entity with id "%s" does not exist.', $id),
            $content['errors'][0]['detail'] ?? null
        );
    }

    public function testSwitchToValidLanguage(): void
    {
        $id = Defaults::LANGUAGE_SYSTEM;

        $this->getChannelBrowser()
            ->request('PATCH', '/store-api/context', ['languageId' => $id]);

        $response = $this->getChannelBrowser()->getResponse();
        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), print_r($content, true));
    }

    public function testSwitchToValidLanguageWithoutDomain(): void
    {
        $id = Uuid::randomHex();

        $browser = $this->createChannelBrowser(null, false, [
            'languageId' => $id,
            'languages' => [
                ['id' => $id],
                ['id' => Defaults::LANGUAGE_SYSTEM],
            ],
            'domains' => [
                [
                    'language' => [
                        'id' => $id,
                        'name' => 'Testlanguage',
                        'localeId' => $this->getLocaleIdOfSystemLanguage(),
                        'active' => true,
                        'parentId' => Defaults::LANGUAGE_SYSTEM,
                    ],
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://someotherdomain',
                ],
            ],
        ]);

        $browser->request('PATCH', '/store-api/context', ['languageId' => Defaults::LANGUAGE_SYSTEM]);

        $response = $browser->getResponse();
        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), print_r($content, true));
        static::assertNull($content['redirectUrl']);
    }

    public function testSwitchToValidCurrency(): void
    {
        $id = Defaults::CURRENCY;

        $this->getChannelBrowser()
            ->request('PATCH', '/store-api/context', ['currencyId' => $id]);

        $response = $this->getChannelBrowser()->getResponse();
        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), print_r($content, true));
    }

    public function testSwitchToNotExistingCurrency(): void
    {
        $id = Uuid::randomHex();

        $this->getChannelBrowser()
            ->request('PATCH', '/store-api/context', ['currencyId' => $id]);

        $response = $this->getChannelBrowser()->getResponse();

        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), print_r($content, true));

        static::assertSame(
            \sprintf('The "currency" entity with id "%s" does not exist.', $id),
            $content['errors'][0]['detail'] ?? null
        );
    }

    private function createUserAndLogin(?string $email = null, string $password = 'contena'): string
    {
        $email ??= Uuid::randomHex() . '@example.com';
        $userId = $this->createUser($password, $email);

        $this->assignChannelContext();

        $this->getChannelBrowser()->request('POST', '/store-api/account/login', [
            'username' => $email,
            'password' => $password,
        ]);

        $response = $this->getChannelBrowser()->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $this->getChannelBrowser()->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        return $userId;
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
            'userNumber' => '12345',
        ];

        $this->userRepository->create([$user], Context::createDefaultContext());

        return $userId;
    }

    private function createUserAddress(string $userId): string
    {
        $addressId = Uuid::randomHex();
        $data = [
            'id' => $addressId,
            'userId' => $userId,
            'firstName' => 'Test',
            'lastName' => 'AdminUser',
            'street' => 'Musterstraße 2',
            'city' => 'Cologne',
            'zipcode' => '89563',
            'salutationId' => $this->getValidSalutationId(),
            'countryId' => $this->getValidCountryId(),
        ];

        $this->userAddressRepository
            ->upsert([$data], Context::createDefaultContext());

        return $addressId;
    }
}
