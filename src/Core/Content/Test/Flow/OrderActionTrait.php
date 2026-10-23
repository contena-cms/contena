<?php declare(strict_types=1);

namespace Contena\Core\Content\Test\Flow;

use Doctrine\DBAL\Connection;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\CountryAddToChannelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\CustomField\CustomFieldTypes;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
trait OrderActionTrait
{
    use CountryAddToChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var ?EntityRepository<UserCollection>
     */
    private ?EntityRepository $userRepository = null;

    private function createUserAndLogin(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $this->prepareUser($email);

        $this->login($email, 'contena');
    }

    /**
     * @param array<string, mixed> $additionalData
     */
    private function prepareUser(?string $email = null, array $additionalData = []): void
    {
        static::assertNotNull($this->userRepository);

        $user = array_merge([
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
                'countryId' => $this->getValidCountryId($this->ids->get('sales-channel')),
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
        ], $additionalData);

        $this->userRepository->create([$user], Context::createDefaultContext());
    }

    private function login(?string $email = null, ?string $password = null): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => $password,
                ]
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }

    private function prepareBlogTest(): void
    {
        static::getContainer()->get('blog.repository')->create([
            [
                'id' => $this->ids->create('p1'),
                'blogNumber' => $this->ids->get('p1'),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->ids->create('manufacturerId'), 'name' => 'test'],
                'tax' => ['id' => $this->ids->create('tax'), 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['channelId' => $this->ids->get('sales-channel'), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], Context::createDefaultContext());
    }

    private function submitOrder(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'type' => 'blog',
                            'referencedId' => $this->ids->get('p1'),
                        ],
                    ],
                ]
            );

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order',
                [
                    'affiliateCode' => 'test affiliate code',
                ]
            );
    }

    private function cancelOrder(): void
    {
        $this->getContainer()->get(SystemConfigService::class)->set('core.cart.enableOrderRefunds', true);

        $this->browser
            ->request(
                'POST',
                '/store-api/order/state/cancel',
                [
                    'orderId' => $this->ids->get('order'),
                ]
            );
    }

    /**
     * @param array<string, mixed> $additionalData
     */
    private function createOrder(string $userId, array $additionalData = []): void
    {
        static::getContainer()->get('order.repository')->create([
            array_merge([
                'id' => $this->ids->create('order'),
                'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
                'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'orderUser' => [
                    'userId' => $userId,
                    'email' => 'test@example.com',
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                ],
                'orderNumber' => Uuid::randomHex(),
                'stateId' => $this->getStateMachineState(),
                'paymentMethodId' => $this->getValidPaymentMethodId(),
                'currencyId' => Defaults::CURRENCY,
                'currencyFactor' => 1.0,
                'channelId' => TestDefaults::CHANNEL,
                'billingAddressId' => $billingAddressId = Uuid::randomHex(),
                'addresses' => [
                    [
                        'id' => $billingAddressId,
                        'salutationId' => $this->getValidSalutationId(),
                        'firstName' => 'Max',
                        'lastName' => 'Mustermann',
                        'street' => 'Ebbinghoff 10',
                        'zipcode' => '48624',
                        'city' => 'Schöppingen',
                        'countryId' => $this->getValidCountryId(),
                    ],
                    [
                        'id' => $this->ids->create('shipping-address'),
                        'countryId' => $this->getValidCountryId(),
                        'salutationId' => $this->getValidSalutationId(),
                        'firstName' => 'Max',
                        'lastName' => 'Mustermann',
                        'street' => 'Ebbinghoff 10',
                        'zipcode' => '48624',
                        'city' => 'Schöppingen',
                    ],
                ],
                'lineItems' => [
                    [
                        'id' => $this->ids->create('line-item'),
                        'identifier' => $this->ids->create('line-item'),
                        'quantity' => 1,
                        'label' => 'label',
                        'type' => LineItem::CUSTOM_LINE_ITEM_TYPE,
                        'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                    ],
                ],
                'deliveries' => [
                    [
                        'id' => $this->ids->create('delivery'),
                        'shippingOrderAddressId' => $this->ids->get('shipping-address'),
                        'shippingMethodId' => $this->getAvailableShippingMethod()->getId(),
                        'stateId' => $this->getStateId('open', 'order_delivery.state'),
                        'trackingCodes' => [],
                        'shippingDateEarliest' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                        'shippingDateLatest' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                        'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'positions' => [
                            [
                                'id' => $this->ids->create('position'),
                                'orderLineItemId' => $this->ids->create('line-item'),
                                'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                            ],
                        ],
                    ],
                ],
                'context' => '{}',
                'payload' => '{}',
            ], $additionalData),
        ], Context::createDefaultContext());
    }

    private function getStateId(string $state, string $machine): string
    {
        return static::getContainer()->get(Connection::class)
            ->fetchOne('
                SELECT LOWER(HEX(state_machine_state.id))
                FROM state_machine_state
                    INNER JOIN  state_machine
                    ON state_machine.id = state_machine_state.state_machine_id
                    AND state_machine.technical_name = :machine
                WHERE state_machine_state.technical_name = :state
            ', [
                'state' => $state,
                'machine' => $machine,
            ]) ?: '';
    }

    private function createCustomField(string $name, string $entity, string $type = CustomFieldTypes::SELECT): string
    {
        $customFieldId = Uuid::randomHex();
        $customFieldSetId = Uuid::randomHex();
        $data = [
            'id' => $customFieldId,
            'name' => $name,
            'type' => $type,
            'customFieldSetId' => $customFieldSetId,
            'config' => [
                'componentName' => 'sw-field',
                'customFieldPosition' => 1,
                'customFieldType' => $type,
                'type' => $type,
                'label' => [
                    'en-GB' => 'lorem_ipsum',
                    'de-DE' => 'lorem_ipsum',
                ],
            ],
            'customFieldSet' => [
                'id' => $customFieldSetId,
                'name' => 'Custom_Field_Set',
                'relations' => [[
                    'id' => Uuid::randomHex(),
                    'customFieldSetId' => $customFieldSetId,
                    'entityName' => $entity,
                ]],
            ],
        ];

        static::getContainer()->get('custom_field.repository')
            ->create([$data], Context::createDefaultContext());

        return $customFieldId;
    }
}
