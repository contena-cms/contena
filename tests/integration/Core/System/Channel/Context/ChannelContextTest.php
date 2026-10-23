<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\Context;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeCollection;
use Contena\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeEntity;
use Contena\Core\System\Tax\TaxRuleType\EntireCountryRuleTypeFilter;
use Contena\Core\System\Tax\TaxRuleType\IndividualStatesRuleTypeFilter;
use Contena\Core\System\Tax\TaxRuleType\ZipCodeRangeRuleTypeFilter;
use Contena\Core\System\Tax\TaxRuleType\ZipCodeRuleTypeFilter;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class ChannelContextTest extends TestCase
{
    use IntegrationTestBehaviour;

    private TaxRuleTypeCollection $taxRuleTypes;

    protected function setUp(): void
    {
        $this->taxRuleTypes = $this->loadTaxRuleTypes();
    }

    public function testGetTaxRuleCollectionWithoutRulesReturnsDefault(): void
    {
        $taxId = Uuid::randomHex();
        $taxData = [
            'id' => $taxId,
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
        ];
        $taxRuleCollection = $this->createChannelContext([$taxData])->buildTaxRules($taxId);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(15.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionUserShippingCountry(): void
    {
        $userId = Uuid::randomHex();
        $shippingCountryId = Uuid::randomHex();
        $this->createUser($userId, $shippingCountryId, Uuid::randomHex());
        $taxId = Uuid::randomHex();
        $entireCountryRuleType = $this->getRuleType(EntireCountryRuleTypeFilter::TECHNICAL_NAME);
        $taxData = [
            'id' => $taxId,
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 10,
                    'activeFrom' => (new \DateTime())->modify('-3 days'),
                    'taxRuleTypeId' => $entireCountryRuleType->getId(),
                ], [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 9,
                    'activeFrom' => (new \DateTime())->modify('-2 days'),
                    'taxRuleTypeId' => $entireCountryRuleType->getId(),
                ], [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 8,
                    'activeFrom' => (new \DateTime())->modify('+3 days'),
                    'taxRuleTypeId' => $entireCountryRuleType->getId(),
                ],
            ],
        ];
        $channelContext = $this->createChannelContext([$taxData], [ChannelContextService::CUSTOMER_ID => $userId]);
        $taxRuleCollection = $channelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(9.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionUserShippingWithMultipleRule(): void
    {
        $userId = Uuid::randomHex();
        $randomCountryId = $this->getValidCountryId();
        $billingCountryId = Uuid::randomHex();
        $shippingCountryId = Uuid::randomHex();
        $this->createUser($userId, $shippingCountryId, $billingCountryId);
        $entireCountryRuleType = $this->getRuleType(EntireCountryRuleTypeFilter::TECHNICAL_NAME);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $billingCountryId,
                    'taxRate' => 10,
                    'taxRuleTypeId' => $entireCountryRuleType->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 9,
                    'taxRuleTypeId' => $entireCountryRuleType->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $randomCountryId,
                    'taxRate' => 8,
                    'taxRuleTypeId' => $entireCountryRuleType->getId(),
                ],
            ],
        ];
        $channelContext = $this->createChannelContext([$taxData], [ChannelContextService::CUSTOMER_ID => $userId]);
        $taxRuleCollection = $channelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(9.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionUserShippingTypeIndividualStatesOverridesEntireCountry(): void
    {
        $userId = Uuid::randomHex();
        $shippingCountryId = Uuid::randomHex();
        $countryStateId = Uuid::randomHex();
        $countryState = ['id' => $countryStateId, 'countryId' => $shippingCountryId, 'shortCode' => Uuid::randomHex(), 'name' => Uuid::randomHex()];
        $this->createUser($userId, $shippingCountryId, Uuid::randomHex(), $countryState);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 10,
                    'taxRuleTypeId' => $this->getRuleType(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 9,
                    'taxRuleTypeId' => $this->getRuleType(IndividualStatesRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'states' => [$countryStateId],
                    ],
                ],
            ],
        ];
        $channelContext = $this->createChannelContext([$taxData], [ChannelContextService::CUSTOMER_ID => $userId]);
        $taxRuleCollection = $channelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(9.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionUserShippingTypeZipCodeRangeOverridesIndividualStates(): void
    {
        $userId = Uuid::randomHex();
        $shippingCountryId = Uuid::randomHex();
        $countryStateId = Uuid::randomHex();
        $countryState = ['id' => $countryStateId, 'countryId' => $shippingCountryId, 'shortCode' => Uuid::randomHex(), 'name' => Uuid::randomHex()];
        $this->createUser($userId, $shippingCountryId, Uuid::randomHex(), $countryState);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 9,
                    'activeFrom' => (new \DateTime())->modify('-2 days'),
                    'taxRuleTypeId' => $this->getRuleType(IndividualStatesRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'states' => [$countryStateId],
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 8,
                    'activeFrom' => (new \DateTime())->modify('-3 days'),
                    'taxRuleTypeId' => $this->getRuleType(ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'fromZipCode' => '12000',
                        'toZipCode' => '12999',
                    ],
                ],
            ],
        ];
        $channelContext = $this->createChannelContext([$taxData], [ChannelContextService::CUSTOMER_ID => $userId]);
        $taxRuleCollection = $channelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(8.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionUserShippingTypeZipCodeOverridesTypeZipCodeRange(): void
    {
        $userId = Uuid::randomHex();
        $shippingCountryId = Uuid::randomHex();
        $countryStateId = Uuid::randomHex();
        $countryState = ['id' => $countryStateId, 'countryId' => $shippingCountryId, 'shortCode' => Uuid::randomHex(), 'name' => Uuid::randomHex()];
        $this->createUser($userId, $shippingCountryId, Uuid::randomHex(), $countryState);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 8,
                    'taxRuleTypeId' => $this->getRuleType(ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'fromZipCode' => '12000',
                        'toZipCode' => '12999',
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 7,
                    'taxRuleTypeId' => $this->getRuleType(ZipCodeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'zipCode' => '12345',
                    ],
                ],
            ],
        ];
        $channelContext = $this->createChannelContext([$taxData], [ChannelContextService::CUSTOMER_ID => $userId]);
        $taxRuleCollection = $channelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(7.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionUserShippingTypeZipCodeOverridesOthers(): void
    {
        $userId = Uuid::randomHex();
        $shippingCountryId = Uuid::randomHex();
        $countryStateId = Uuid::randomHex();
        $countryState = ['id' => $countryStateId, 'countryId' => $shippingCountryId, 'shortCode' => Uuid::randomHex(), 'name' => Uuid::randomHex()];
        $this->createUser($userId, $shippingCountryId, Uuid::randomHex(), $countryState);
        $taxData = $this->getTaxData($shippingCountryId, $countryStateId);
        $channelContext = $this->createChannelContext([$taxData], [ChannelContextService::CUSTOMER_ID => $userId]);
        $taxRuleCollection = $channelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(7.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionDefaultOnWrongCountry(): void
    {
        $userId = Uuid::randomHex();
        $shippingCountryId = $this->getValidCountryId();
        $countryStateId = Uuid::randomHex();
        $countryState = ['id' => $countryStateId, 'countryId' => $shippingCountryId, 'shortCode' => Uuid::randomHex(), 'name' => Uuid::randomHex()];
        $this->createUser($userId, Uuid::randomHex(), Uuid::randomHex(), $countryState);
        $taxData = $this->getTaxData($shippingCountryId, $countryStateId);
        $channelContext = $this->createChannelContext([$taxData], [ChannelContextService::CUSTOMER_ID => $userId]);
        $taxRuleCollection = $channelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(15.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionNoUserDefaultShippingLocationCountry(): void
    {
        $shippingCountryId = $this->getValidCountryId();
        $taxId = Uuid::randomHex();
        $taxData = [
            'id' => $taxId,
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 10,
                    'taxRuleTypeId' => $this->getRuleType(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
            ],
        ];
        $channelContext = $this->createChannelContext([$taxData], [ChannelContextService::COUNTRY_ID => $shippingCountryId]);
        $taxRuleCollection = $channelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(10.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionNoUserDefaultShippingLocationWithMultipleRule(): void
    {
        $countryIds = $this->getValidCountryIds(3);
        $shippingCountryId = $countryIds[0];
        $entireCountryRuleType = $this->getRuleType(EntireCountryRuleTypeFilter::TECHNICAL_NAME);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 10,
                    'taxRuleTypeId' => $entireCountryRuleType->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $countryIds[1],
                    'taxRate' => 9,
                    'taxRuleTypeId' => $entireCountryRuleType->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $countryIds[2],
                    'taxRate' => 8,
                    'taxRuleTypeId' => $entireCountryRuleType->getId(),
                ],
            ],
        ];
        $channelContext = $this->createChannelContext([$taxData], [ChannelContextService::COUNTRY_ID => $shippingCountryId]);
        $taxRuleCollection = $channelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(10.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionNoUserDefaultShippingLocationTypeIndividualStatesOverridesEntireCountry(): void
    {
        $shippingCountryId = $this->getValidCountryId();
        $countryStateId = $this->createCountryState($shippingCountryId);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 10,
                    'taxRuleTypeId' => $this->getRuleType(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 9,
                    'taxRuleTypeId' => $this->getRuleType(IndividualStatesRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'states' => [$countryStateId],
                    ],
                ],
            ],
        ];
        $channelContext = $this->createChannelContext([$taxData], [ChannelContextService::COUNTRY_ID => $shippingCountryId, ChannelContextService::COUNTRY_STATE_ID => $countryStateId]);
        $taxRuleCollection = $channelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(9.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionNoUserDefaultShippingLocationTypeZipCodeRangeDoesNotMatch(): void
    {
        $shippingCountryId = $this->getValidCountryId();
        $zipCodeRangeRuleType = $this->getRuleType(ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME);
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 8,
                    'taxRuleTypeId' => $zipCodeRangeRuleType->getId(),
                    'data' => [
                        'fromZipCode' => '12000',
                        'toZipCode' => '12999',
                    ],
                ],
            ],
        ];
        $channelContext = $this->createChannelContext([$taxData], [ChannelContextService::COUNTRY_ID => $shippingCountryId]);
        $taxRuleCollection = $channelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(15.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionNoUserDefaultShippingLocationTypeZipCodeDoesNotMatch(): void
    {
        $shippingCountryId = $this->getValidCountryId();
        $taxData = [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 8,
                    'taxRuleTypeId' => $this->getRuleType(ZipCodeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'zipCode' => '12345',
                    ],
                ],
            ],
        ];
        $channelContext = $this->createChannelContext([$taxData], [ChannelContextService::COUNTRY_ID => $shippingCountryId]);
        $taxRuleCollection = $channelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(15.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionNoUserDefaultShippingLocationTypeIndividualStatesOverridesOthers(): void
    {
        $shippingCountryId = $this->getValidCountryId();
        $countryStateId = $this->createCountryState($shippingCountryId);
        $taxData = $this->getTaxData($shippingCountryId, $countryStateId);
        $channelContext = $this->createChannelContext([$taxData], [ChannelContextService::COUNTRY_ID => $shippingCountryId, ChannelContextService::COUNTRY_STATE_ID => $countryStateId]);
        $taxRuleCollection = $channelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(9.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetTaxRuleCollectionNoUserDefaultShippingLocationDefaultOnWrongCountry(): void
    {
        $countryIds = $this->getValidCountryIds(2);
        $shippingCountryId = $countryIds[0];
        $countryStateId = $this->createCountryState($shippingCountryId);
        $taxData = $this->getTaxData($countryIds[1], $countryStateId);
        $channelContext = $this->createChannelContext([$taxData], [ChannelContextService::COUNTRY_ID => $shippingCountryId, ChannelContextService::COUNTRY_STATE_ID => $countryStateId]);
        $taxRuleCollection = $channelContext->buildTaxRules($taxData['id']);

        static::assertCount(1, $taxRuleCollection);
        static::assertSame(15.0, $taxRuleCollection->first()?->getTaxRate());
        static::assertSame(100.0, $taxRuleCollection->first()->getPercentage());
    }

    public function testGetShippingMethodNoFound(): void
    {
        $shippingMethodIdNoExits = '25c5b40b1cb643288ae8e703c2997666';

        $channelContext = $this->createChannelContext([], [ChannelContextService::SHIPPING_METHOD_ID => $shippingMethodIdNoExits]);

        /** @var EntityRepository<ChannelCollection> $repository */
        $repository = static::getContainer()->get('channel.repository');
        $channel = $repository->search(new Criteria([$channelContext->getChannelId()]), $channelContext->getContext())->getEntities()->first();
        static::assertNotNull($channel);

        static::assertSame($channel->getShippingMethodId(), $channelContext->getChannel()->getShippingMethodId());
        static::assertNotSame($shippingMethodIdNoExits, $channelContext->getChannel()->getShippingMethodId());
    }

    #[DataProvider('ensureLoginExceptionProvider')]
    public function testEnsureLoginShouldThrowAnException(bool $login, bool $isGuest, bool $allowGuest): void
    {
        $options = [];

        if ($login) {
            $userId = Uuid::randomHex();
            $this->createUser($userId, null, null, [], $isGuest);

            $options[ChannelContextService::CUSTOMER_ID] = $userId;
        }

        $channelContext = $this->createChannelContext([], $options);

        $this->expectExceptionObject(ChannelException::userNotLoggedIn());
        $channelContext->ensureLoggedIn($allowGuest);
    }

    public static function ensureLoginExceptionProvider(): \Generator
    {
        yield 'Not logged in' => [
            false,
            false,
            false,
        ];

        yield 'Logged in as guest, but guest not allowed' => [
            true,
            true,
            false,
        ];
    }

    #[DataProvider('ensureLoginPassProvider')]
    public function testEnsureLoginShouldPass(bool $login, bool $isGuest, bool $allowGuest): void
    {
        $options = [];

        if ($login) {
            $userId = Uuid::randomHex();
            $this->createUser($userId, null, null, [], $isGuest);

            $options[ChannelContextService::CUSTOMER_ID] = $userId;
        }

        $channelContext = $this->createChannelContext([], $options);

        $error = null;
        $message = '';
        try {
            $channelContext->ensureLoggedIn($allowGuest);
        } catch (\Throwable $e) {
            $error = $e;
            $message = \sprintf('No error expected, got "%s" with: %s', $error->getMessage(), $error->getTraceAsString());
        }
        static::assertNull($error, $message);
    }

    public static function ensureLoginPassProvider(): \Generator
    {
        yield 'Logged in as guest and guest is allowed' => [
            true,
            true,
            true,
        ];

        yield 'Logged in and guest is allowed' => [
            true,
            false,
            true,
        ];

        yield 'Logged in and guest is not allowed' => [
            true,
            false,
            false,
        ];
    }

    /**
     * @return list<string>
     */
    protected function getValidCountryIds(int $limit): array
    {
        $criteria = (new Criteria())->setLimit($limit);

        return static::getContainer()->get('country.repository')->searchIds($criteria, Context::createDefaultContext())->getIds();
    }

    protected function createCountryState(string $countryId): string
    {
        $id = Uuid::randomHex();

        static::getContainer()->get('country_state.repository')->create(
            [['id' => $id, 'countryId' => $countryId, 'shortCode' => Uuid::randomHex(), 'name' => Uuid::randomHex()]],
            Context::createDefaultContext()
        );

        return $id;
    }

    /**
     * @param array<int, array<string, mixed>> $taxData
     * @param array<string, mixed> $options
     */
    private function createChannelContext(array $taxData = [], array $options = []): ChannelContext
    {
        if ($taxData !== []) {
            static::getContainer()->get('tax.repository')->create($taxData, Context::createDefaultContext());
        }

        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);

        $token = Uuid::randomHex();

        return $channelContextFactory->create($token, TestDefaults::CHANNEL, $options);
    }

    private function loadTaxRuleTypes(): TaxRuleTypeCollection
    {
        /** @var EntityRepository<TaxRuleTypeCollection> $taxRuleTypeRepository */
        $taxRuleTypeRepository = static::getContainer()->get('tax_rule_type.repository');

        return $taxRuleTypeRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();
    }

    /**
     * @param array<string, mixed>|null $countryState
     */
    private function createUser(
        string $userId,
        ?string $shippingCountryId = null,
        ?string $billingCountryId = null,
        ?array $countryState = null,
        bool $isGuest = false
    ): void {
        $userRepository = static::getContainer()->get('user.repository');
        $salutationId = $this->getValidSalutationId();

        $billingAddress = [
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'street' => 'Musterstraße 1',
            'city' => 'Schöppingen',
            'zipcode' => '12345',
            'salutationId' => $salutationId,
            'country' => ['id' => $billingCountryId ?? Uuid::randomHex(), 'name' => 'Germany'],
        ];

        $shippingAddress = [
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'street' => 'Musterstraße 1',
            'city' => 'Schöppingen',
            'zipcode' => '12345',
            'salutationId' => $salutationId,
            'country' => ['id' => $shippingCountryId ?? Uuid::randomHex(), 'name' => 'Germany'],
        ];

        if ($countryState) {
            $billingAddress['countryState'] = $countryState;
            $shippingAddress['countryState'] = $countryState;
        }

        $user = [
            'id' => $userId,
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => $shippingAddress,
            'defaultBillingAddress' => $billingAddress,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'guest' => $isGuest,
            'password' => '$password',
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $salutationId,
            'userNumber' => '12345',
        ];

        $userRepository->create([$user], Context::createDefaultContext());
    }

    private function getRuleType(string $technicalName): TaxRuleTypeEntity
    {
        $ruleType = $this->taxRuleTypes->getByTechnicalName($technicalName);
        static::assertNotNull($ruleType);

        return $ruleType;
    }

    /**
     * @return array{id: string, taxRate: float, name: string, rules: list<array{id: string, countryId: string, taxRate: float, taxRuleTypeId: string, data?: array<string, mixed>}>}
     */
    private function getTaxData(string $shippingCountryId, string $countryStateId): array
    {
        return [
            'id' => Uuid::randomHex(),
            'taxRate' => 15,
            'name' => Uuid::randomHex(),
            'rules' => [
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 10,
                    'taxRuleTypeId' => $this->getRuleType(EntireCountryRuleTypeFilter::TECHNICAL_NAME)->getId(),
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 9,
                    'taxRuleTypeId' => $this->getRuleType(IndividualStatesRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'states' => [$countryStateId],
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 8,
                    'taxRuleTypeId' => $this->getRuleType(ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'fromZipCode' => '12000',
                        'toZipCode' => '12999',
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'countryId' => $shippingCountryId,
                    'taxRate' => 7,
                    'taxRuleTypeId' => $this->getRuleType(ZipCodeRuleTypeFilter::TECHNICAL_NAME)->getId(),
                    'data' => [
                        'zipCode' => '12345',
                    ],
                ],
            ],
        ];
    }
}
