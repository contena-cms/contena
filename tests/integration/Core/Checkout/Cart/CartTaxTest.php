<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Util\FloatComparator;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Currency\CurrencyCollection;
use Contena\Core\System\DeliveryTime\DeliveryTimeEntity;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
class CartTaxTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    private Connection $connection;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    /**
     * @var EntityRepository<CountryCollection>
     */
    private EntityRepository $countryRepository;

    /**
     * @var EntityRepository<CurrencyCollection>
     */
    private EntityRepository $currencyRepository;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->connection = static::getContainer()->get(Connection::class);
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->userRepository = static::getContainer()->get('user.repository');
        $this->countryRepository = static::getContainer()->get('country.repository');
        $this->currencyRepository = static::getContainer()->get('currency.repository');
    }

    /**
     * @param array<string> $vatIds
     */
    #[DataProvider('dataTestHandlingTaxFreeInFrontend')]
    public function testHandlingTaxFreeInFrontendWithBaseCurrencyEuro(
        string $testCase,
        float $currencyTaxFreeFrom,
        bool $countryTaxFree,
        bool $countryCompanyTaxFree,
        float $countryTaxFreeFrom,
        float $countryCompanyTaxFreeFrom,
        int $quantity,
        ?array $vatIds = null,
        bool $checkVatIdPattern = true
    ): void {
        $this->createShippingMethod();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'shippingMethodId' => $this->ids->get('shipping'),
        ]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));

        $this->createBlog();

        $countryId = Uuid::fromBytesToHex($this->getCountryIdByIso());

        $this->createUserAndLogin($countryId);

        if ($vatIds) {
            $this->userRepository->update(
                [['id' => $this->ids->get('user'), 'vatIds' => $vatIds]],
                Context::createDefaultContext()
            );
        }

        $this->currencyRepository->update([[
            'id' => Defaults::CURRENCY,
            'taxFreeFrom' => $currencyTaxFreeFrom,
        ]], Context::createDefaultContext());

        $this->updateCountry(
            $countryId,
            $countryTaxFree,
            $countryTaxFreeFrom,
            $countryCompanyTaxFree,
            $countryCompanyTaxFreeFrom,
            Defaults::CURRENCY,
            $checkVatIdPattern
        );

        $this->browser->request(
            'POST',
            '/store-api/checkout/cart/line-item',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode([
                'items' => [
                    [
                        'id' => $this->ids->get('p1'),
                        'type' => 'blog',
                        'referencedId' => $this->ids->get('p1'),
                        'quantity' => $quantity,
                    ],
                ],
            ])
        );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        if ($testCase === 'tax-free') {
            static::assertSame((500 * $quantity) + 10, $response['price']['totalPrice']);
        } else {
            static::assertSame((550 * $quantity) + 11, $response['price']['totalPrice']);
        }
    }

    /**
     * @param array<string> $vatIds
     */
    #[DataProvider('dataTestHandlingTaxFreeInFrontend')]
    public function testHandlingTaxFreeInFrontendWithBaseCurrencyCHF(
        string $testCase,
        float $currencyTaxFreeFrom,
        bool $countryTaxFree,
        bool $countryCompanyTaxFree,
        float $countryTaxFreeFrom,
        float $countryCompanyTaxFreeFrom,
        int $quantity,
        ?array $vatIds = null,
        bool $checkVatIdPattern = true
    ): void {
        $currencyId = Uuid::fromBytesToHex($this->getCurrencyIdByIso('CHF'));

        $this->createShippingMethod();
        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'currencyId' => $currencyId,
            'shippingMethodId' => $this->ids->get('shipping'),
        ]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));

        $this->createBlog();

        $countryId = Uuid::fromBytesToHex($this->getCountryIdByIso('CH'));

        $this->createUserAndLogin($countryId);

        if ($vatIds) {
            $this->userRepository->update(
                [['id' => $this->ids->get('user'), 'vatIds' => $vatIds]],
                Context::createDefaultContext()
            );
        }

        $this->currencyRepository->update([[
            'id' => $currencyId,
            'taxFreeFrom' => $currencyTaxFreeFrom,
        ]], Context::createDefaultContext());

        $this->updateCountry(
            $countryId,
            $countryTaxFree,
            $countryTaxFreeFrom,
            $countryCompanyTaxFree,
            $countryCompanyTaxFreeFrom,
            Defaults::CURRENCY,
            $checkVatIdPattern
        );

        $this->browser->request(
            'POST',
            '/store-api/checkout/cart/line-item',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode([
                'items' => [
                    [
                        'id' => $this->ids->get('p1'),
                        'type' => 'blog',
                        'referencedId' => $this->ids->get('p1'),
                        'quantity' => $quantity,
                    ],
                ],
            ])
        );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        if ($testCase === 'tax-free') {
            static::assertSame((550 * $quantity) + 11, $response['price']['totalPrice']);
        } else {
            static::assertSame((605 * $quantity) + 12.1, $response['price']['totalPrice']);
        }
    }

    #[DataProvider('dataTestHandlingTaxFreeInFrontendWithCountryBaseCurrencyUSD')]
    public function testHandlingTaxFreeInFrontendWithCountryBaseCurrencyUSD(
        string $testCase,
        bool $countryTaxFree,
        bool $countryCompanyTaxFree,
        float $countryTaxFreeFrom,
        float $countryCompanyTaxFreeFrom,
        int $quantity
    ): void {
        $currencyId = Uuid::fromBytesToHex($this->getCurrencyIdByIso('USD'));

        $this->createShippingMethod();
        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'currencyId' => $currencyId,
            'shippingMethodId' => $this->ids->get('shipping'),
        ]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));

        $this->createBlog();

        $usCountryId = Uuid::fromBytesToHex($this->getCountryIdByIso('US'));

        $this->createUserAndLogin($usCountryId);

        $this->updateCountry(
            $usCountryId,
            $countryTaxFree,
            $countryTaxFreeFrom,
            $countryCompanyTaxFree,
            $countryCompanyTaxFreeFrom,
            $currencyId
        );

        $this->browser->request(
            'POST',
            '/store-api/checkout/cart/line-item',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode([
                'items' => [
                    [
                        'id' => $this->ids->get('p1'),
                        'type' => 'blog',
                        'referencedId' => $this->ids->get('p1'),
                        'quantity' => $quantity,
                    ],
                ],
            ])
        );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        if ($testCase === 'tax-free') {
            static::assertSame(FloatComparator::cast((585.43 * $quantity) + 11.71), $response['price']['totalPrice']);
        } else {
            static::assertSame(FloatComparator::cast((643.97 * $quantity) + 12.88), $response['price']['totalPrice']);
        }
    }

    /**
     * string $testCase
     * bool $countryTaxFree
     * bool $countryCompanyTaxFree
     * float $countryTaxFreeFrom
     * float $countryCompanyTaxFreeFrom
     * int $quantity
     *
     * @return array<mixed>
     */
    public static function dataTestHandlingTaxFreeInFrontendWithCountryBaseCurrencyUSD(): array
    {
        return [
            'case 1 tax-free' => ['tax-free', true, false, 100, 100, 1],
            'case 2 tax-free' => ['tax-free', true, false, 1000, 100, 2],
            'case 3 tax-free' => ['no tax-free', true, true, 1000, 100, 1],
            'case 4 tax-free' => ['tax-free', true, true, 1000, 1000, 2],
            'case 5 no-tax-free' => ['no-tax-free', true, false, 1000, 100, 1],
            'case 6 no-tax-free' => ['no-tax-free', true, true, 1000, 1000, 1],
            'case 7 no-tax-free' => ['no-tax-free', false, false, 1000, 1000, 1],
            'case 8 no-tax-free' => ['no-tax-free', false, false, 1000, 1000, 2],
            'case 9 tax-free' => ['no tax-free', false, true, 100, 100, 1],
            'case 10 tax-free' => ['no tax-free', false, true, 100, 1000, 2],
            'case 11 tax-free' => ['tax-free', true, true, 100, 1000, 1],
        ];
    }

    /**
     * string $testCase
     * float $currencyTaxFreeFrom
     * bool $countryTaxFree
     * bool $countryCompanyTaxFree
     * float $countryTaxFreeFrom
     * float $countryCompanyTaxFreeFrom
     * int $quantity
     * ?array vatIds
     * ?bool checkVatIdPattern
     *
     * @return array<array<mixed>>
     */
    public static function dataTestHandlingTaxFreeInFrontend(): array
    {
        return [
            'case 1 tax-free' => ['tax-free', 500, false, false, 0, 0, 1],
            'case 2 tax-free' => ['tax-free', 1000, false, false, 0, 0, 2],
            'case 3 no tax-free' => ['no tax-free', 1000, false, false, 0, 0, 1],
            'case 4 no tax-free' => ['no tax-free', 1000, true, false, 100, 0,  1],
            'case 5 no tax-free' => ['no tax-free', 1000, true, true, 100, 0, 1],
            'case 6 no tax-free' => ['no tax-free', 1000, false, true, 100, 0, 1],
            'case 7 no tax-free' => ['no tax-free', 1000, false, true, 100, 100, 1],
            'case 8 no tax-free' => ['no tax-free', 1000, true, false, 100, 100,  1],
            'case 9 no tax-free' => ['no tax-free', 1000, true, true, 100, 100, 1],
            'case 10 tax-free' => ['tax-free', 0, true, true, 100, 100, 1],
            'case 11 tax-free' => ['no tax-free', 0, false, true, 100, 100, 1],
            'case 12 tax-free' => ['no tax-free', 0, false, true, 0, 100, 1],
            'case 13 tax-free' => ['no tax-free', 0, false, true, 1000, 100, 1],
            'case 14 tax-free' => ['tax-free', 0, true, false, 100, 100, 1],
            'case 15 tax-free' => ['tax-free', 0, true, false, 100, 1000, 1],
            'case 16 tax-free' => ['tax-free', 0, true, false, 100, 0, 1],
            'case 17 tax-free' => ['tax-free', 0, true, false, 1000, 0, 2],
            'case 18 tax-free' => ['no tax-free', 0, false, true, 0, 1000, 2],
            'case 19 tax-free' => ['no tax-free', 0, false, true, 0, 999.99, 3],
            'case 20 tax-free' => ['tax-free', 0, true, false, 1000, 0, 3],
            'case 21 no tax-free' => ['no tax-free', 0, true, true, 1000, 100, 1, ['DE1234567890123'], true],
            'case 22 tax-free' => ['no tax-free', 0, false, true, 1000, 100, 1, ['DE1234567890123'], false],
            'case 23 tax-free' => ['no tax-free', 0, false, true, 1000, 100, 1, ['DE123456789'], false],
            'case 24 tax-free' => ['no tax-free', 0, false, true, 1000, 100, 1, ['DE123456789'], true],
        ];
    }

    private function createBlog(): void
    {
        $this->blogRepository->create([
            [
                'id' => $this->ids->create('p1'),
                'blogNumber' => $this->ids->get('p1'),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 550, 'net' => 500, 'linked' => false]],
                'manufacturer' => ['id' => $this->ids->create('manufacturerId'), 'name' => 'test'],
                'tax' => ['id' => $this->ids->create('tax'), 'taxRate' => 10, 'name' => 'standard'],
                'active' => true,
                'visibilities' => [
                    ['channelId' => $this->ids->get('sales-channel'), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], Context::createDefaultContext());
    }

    private function createUserAndLogin(string $countryId): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $this->createUser($countryId, $email);

        $this->login($email, 'contena');
    }

    private function login(?string $email = null, ?string $password = null): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                (string) json_encode([
                    'email' => $email,
                    'password' => $password,
                ], \JSON_THROW_ON_ERROR)
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }

    private function createUser(string $countryId, string $email): void
    {
        $user = [
            'id' => $this->ids->create('user'),
            'channelId' => $this->ids->get('sales-channel'),
            'defaultShippingAddress' => [
                'id' => $this->ids->create('address'),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $countryId,
            ],
            'defaultBillingAddressId' => $this->ids->get('address'),
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => '12345',
            'vatIds' => ['DE123456789'],
            'company' => 'Test',
        ];

        $this->userRepository->create([$user], Context::createDefaultContext());
    }

    private function updateCountry(
        string $countryId,
        bool $countryTaxFree,
        float $countryTaxFreeFrom,
        bool $countryCompanyTaxFree,
        float $countryCompanyTaxFreeFrom,
        string $currencyId = Defaults::CURRENCY,
        bool $checkVatIdPattern = true
    ): void {
        $this->countryRepository->update([[
            'id' => $countryId,
            'userTax' => [
                'enabled' => $countryTaxFree,
                'currencyId' => $currencyId,
                'amount' => $countryTaxFreeFrom,
            ],
            'companyTax' => [
                'enabled' => $countryCompanyTaxFree,
                'currencyId' => $currencyId,
                'amount' => $countryCompanyTaxFreeFrom,
            ],
            'vatIdPattern' => '(DE)?[0-9]{9}',
            'checkVatIdPattern' => $checkVatIdPattern,
        ]], Context::createDefaultContext());
    }

    private function getCountryIdByIso(string $iso = 'DE'): string
    {
        return $this->connection->fetchOne('SELECT id FROM country WHERE iso = :iso', ['iso' => $iso]);
    }

    private function getCurrencyIdByIso(string $iso = 'EUR'): string
    {
        return $this->connection->fetchOne('SELECT id FROM currency WHERE iso_code = :iso', ['iso' => $iso]);
    }

    private function createShippingMethod(): void
    {
        $data = [
            [
                'id' => $this->ids->create('shipping'),
                'active' => true,
                'bindShippingfree' => false,
                'name' => 'test',
                'technicalName' => 'shipping_test',
                'availabilityRule' => [
                    'id' => $this->ids->create('rule'),
                    'name' => 'asd',
                    'priority' => 2,
                ],
                'deliveryTime' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'testDeliveryTime',
                    'min' => 1,
                    'max' => 90,
                    'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
                ],
                'prices' => [
                    [
                        'name' => 'Test',
                        'price' => 10,
                        'currencyId' => Defaults::CURRENCY,
                        'calculation' => 1,
                        'quantityStart' => 1,
                        'currencyPrice' => [
                            [
                                'currencyId' => Defaults::CURRENCY,
                                'net' => 10,
                                'gross' => 11,
                                'linked' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        static::getContainer()->get('shipping_method.repository')
            ->create($data, Context::createDefaultContext());
    }
}
